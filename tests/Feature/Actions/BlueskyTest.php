<?php

use App\Actions\Api\Bluesky\CreatePost;
use App\Actions\Api\Bluesky\RefreshToken;
use App\Enums\ConnectionType;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    $this->user = User::factory()->create();
});

it('logs in with the configured app password when there is no session yet', function () {
    fakeBluesky();

    (new RefreshToken)->handle($this->user);

    $connection = $this->user->connections->firstWhere('type', ConnectionType::BLUESKY->value);

    expect($connection->external_id)->toBe('did:plc:spacelampsix')
        ->and($connection->token)->toBe(blueskySession()['accessJwt'])
        ->and($connection->refresh_token)->toBe(blueskySession()['refreshJwt']);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'createSession')
        && $request['identifier'] === 'spacelampsix.bsky.social'
        && $request['password'] === 'test-app-password');

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), 'refreshSession'));
});

it('refreshes an existing session without a request body', function () {
    Connection::factory()->bluesky()->for($this->user)->create(['refresh_token' => 'stale-refresh-token']);

    fakeBluesky();

    (new RefreshToken)->handle($this->user);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'refreshSession')
        && $request->hasHeader('Authorization', 'Bearer stale-refresh-token')
        && $request->body() === '');

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), 'createSession'));

    expect($this->user->connections()->sole()->refresh_token)->toBe(blueskySession()['refreshJwt']);
});

it('logs in again once the refresh token has expired', function () {
    Connection::factory()->bluesky()->for($this->user)->create();

    fakeBluesky([
        BLUESKY_SERVICE.'/xrpc/com.atproto.server.refreshSession' => Http::response(['error' => 'ExpiredToken'], 400),
        BLUESKY_SERVICE.'/xrpc/com.atproto.server.createSession' => Http::response(blueskySession('relogin')),
    ]);

    (new CreatePost)->publish($this->user, 'Hello');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'createRecord')
        && $request->hasHeader('Authorization', 'Bearer '.blueskySession('relogin')['accessJwt']));

    expect($this->user->connections()->count())->toBe(1);
});

it('marks links by their utf-8 byte range and returns the post url', function () {
    Connection::factory()->bluesky()->for($this->user)->create();

    fakeBluesky();

    $body = "🔴 Café time!\n\nhttps://twitch.tv/spacelampsix.";

    $published = (new CreatePost)->publish($this->user, $body);

    expect($published)->toBe([
        'external_id' => 'at://did:plc:spacelampsix/app.bsky.feed.post/3kpost',
        'url' => BLUESKY_POST_URL,
    ]);

    Http::assertSent(function (Request $request) use ($body) {
        if (! str_ends_with($request->url(), 'createRecord')) {
            return false;
        }

        $byteStart = strlen("🔴 Café time!\n\n");

        expect($request['repo'])->toBe('did:plc:spacelampsix')
            ->and($request['collection'])->toBe('app.bsky.feed.post')
            ->and($request['record']['text'])->toBe($body)
            ->and($request['record']['facets'])->toBe([[
                'index' => ['byteStart' => $byteStart, 'byteEnd' => $byteStart + strlen('https://twitch.tv/spacelampsix')],
                'features' => [['$type' => 'app.bsky.richtext.facet#link', 'uri' => 'https://twitch.tv/spacelampsix']],
            ]])
            ->and($request['record'])->not->toHaveKey('embed');

        return true;
    });
});

it('attaches a link card with the uploaded thumbnail', function () {
    Connection::factory()->bluesky()->for($this->user)->create();

    fakeBluesky();

    (new CreatePost)->publish($this->user, 'Hello', blueskyLinkCard());

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'uploadBlob')
        && $request->hasHeader('Content-Type', 'image/jpeg')
        && $request->body() === 'jpeg-bytes');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'createRecord')
        && $request['record']['embed'] === [
            '$type' => 'app.bsky.embed.external',
            'external' => [
                'uri' => 'https://twitch.tv/spacelampsix',
                'title' => 'Building a castle',
                'description' => 'Playing Minecraft on Twitch',
                'thumb' => [
                    '$type' => 'blob',
                    'ref' => ['$link' => 'bafkreithumbnail'],
                    'mimeType' => 'image/jpeg',
                    'size' => 10,
                ],
            ],
        ]);
});

it('posts the card without an image while twitch has no preview yet', function () {
    Connection::factory()->bluesky()->for($this->user)->create();

    fakeBluesky([
        'static-cdn.jtvnw.net/*' => Http::response('', 302, ['Location' => 'https://static-cdn.jtvnw.net/ttv-static/404_preview-1280x720.jpg']),
    ]);

    (new CreatePost)->publish($this->user, 'Hello', blueskyLinkCard());

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), 'uploadBlob'));

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'createRecord')
        && ! array_key_exists('thumb', $request['record']['embed']['external']));
});

it('posts the card without an image when bluesky rejects the upload', function () {
    Connection::factory()->bluesky()->for($this->user)->create();

    fakeBluesky([
        BLUESKY_SERVICE.'/xrpc/com.atproto.repo.uploadBlob' => Http::response(['error' => 'BlobTooLarge'], 400),
    ]);

    (new CreatePost)->publish($this->user, 'Hello', blueskyLinkCard());

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'createRecord')
        && ! array_key_exists('thumb', $request['record']['embed']['external']));
});

/**
 * @return array{uri: string, title: string, description: string, thumbnail: string}
 */
function blueskyLinkCard(): array
{
    return [
        'uri' => 'https://twitch.tv/spacelampsix',
        'title' => 'Building a castle',
        'description' => 'Playing Minecraft on Twitch',
        'thumbnail' => 'https://static-cdn.jtvnw.net/previews-ttv/live_user_spacelampsix-1280x720.jpg',
    ];
}
