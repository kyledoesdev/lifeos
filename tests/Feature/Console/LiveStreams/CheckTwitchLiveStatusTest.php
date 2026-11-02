<?php

use App\Enums\SocialPlatform;
use App\Enums\SocialPostStatus;
use App\Models\Connection;
use App\Models\LiveStream;
use App\Models\SocialMediaPost;
use App\Models\StreamCategoryRule;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;

const LIVE_NOW_WEBHOOK = 'https://discord.com/api/webhooks/live-now';

beforeEach(function () {
    config()->set('services.twitch.channel', 'spacelampsix');
    config()->set('services.discord.song-rank.live-now.webhook_url', LIVE_NOW_WEBHOOK);
    config()->set('logging.channels.discord-internal-updates', [
        'driver' => 'monolog',
        'handler' => TestHandler::class,
    ]);

    Http::preventStrayRequests();

    $this->user = User::factory()->create();

    Connection::factory()->twitch()->for($this->user)->create();
});

it('announces a new stream on discord and records it', function () {
    fakeTwitchLiveCheck(helixStream());

    $this->artisan('twitch:check-live')->assertSuccessful();

    $stream = LiveStream::query()->sole();

    expect($stream->external_id)->toBe('40001')
        ->and($stream->title)->toBe('Building a castle')
        ->and($stream->category_id)->toBe('27471')
        ->and($stream->category_name)->toBe('Minecraft')
        ->and($stream->started_at->toIso8601ZuluString())->toBe('2026-11-02T18:00:00Z')
        ->and($stream->discord_notified_at)->not->toBeNull()
        ->and($stream->ended_at)->toBeNull();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api.twitch.tv/helix/streams')
        && $request->hasHeader('Authorization', 'Bearer fresh-twitch-token')
        && $request['user_login'] === 'spacelampsix');

    Http::assertSent(fn (Request $request) => $request->url() === LIVE_NOW_WEBHOOK
        && str_contains($request['content'], '@everyone')
        && str_contains($request['content'], '**Building a castle**')
        && str_contains($request['content'], 'Playing: Minecraft')
        && str_contains($request['content'], 'https://twitch.tv/spacelampsix')
        && $request['allowed_mentions'] === ['parse' => ['everyone']]);

    expect(internalUpdates()->hasInfoThatContains('Discord notified of live stream successfully. Have a great stream!'))->toBeTrue()
        ->and(internalUpdates()->hasInfoThatContains('Bluesky: skipped (switched off)'))->toBeTrue();
});

it('does nothing for a stream it has already seen, even when discord failed', function () {
    LiveStream::factory()->create(['external_id' => '40001', 'discord_notified_at' => null]);

    fakeTwitchLiveCheck(helixStream());

    $this->artisan('twitch:check-live')
        ->expectsOutput('Already notified for this stream session.')
        ->assertSuccessful();

    Http::assertNotSent(fn (Request $request) => $request->url() === LIVE_NOW_WEBHOOK);

    expect(LiveStream::query()->count())->toBe(1)
        ->and(internalUpdates()->getRecords())->toBeEmpty();
});

it('records a failed discord announcement without reporting it', function () {
    fakeTwitchLiveCheck(helixStream(), [LIVE_NOW_WEBHOOK => Http::response('', 500)]);

    $this->artisan('twitch:check-live')->assertSuccessful();

    expect(LiveStream::query()->sole()->discord_notified_at)->toBeNull()
        ->and(internalUpdates()->hasInfoThatContains('Discord'))->toBeFalse()
        ->and(internalUpdates()->hasInfoThatContains('Bluesky: skipped (switched off)'))->toBeTrue();
});

it('closes the open stream once the channel goes offline', function () {
    $live = LiveStream::factory()->create();
    $legacy = LiveStream::factory()->legacy()->create();

    fakeTwitchLiveCheck(null);

    $this->artisan('twitch:check-live')
        ->expectsOutput('spacelampsix is not currently live.')
        ->assertSuccessful();

    expect($live->fresh()->ended_at)->not->toBeNull()
        ->and($legacy->fresh()->ended_at)->toBeNull();

    Http::assertNotSent(fn (Request $request) => $request->url() === LIVE_NOW_WEBHOOK);
});

it('fails when twitch is not connected', function () {
    Connection::query()->delete();

    $this->artisan('twitch:check-live')->assertFailed();

    Http::assertNothingSent();
});

it('fails instead of treating a twitch error as being offline', function () {
    $live = LiveStream::factory()->create();

    fakeTwitchLiveCheck(helixStream(), ['api.twitch.tv/helix/streams*' => Http::response([], 401)]);

    $this->artisan('twitch:check-live')->assertFailed();

    expect($live->fresh()->ended_at)->toBeNull();
});

it('posts to bluesky when the category rule allows it', function () {
    enableBluesky($this->user);
    StreamCategoryRule::factory()->minecraft()->create();

    fakeTwitchLiveCheck(helixStream());
    fakeBluesky();

    $this->artisan('twitch:check-live')->assertSuccessful();

    $post = SocialMediaPost::query()->sole();

    expect($post->postable->is(LiveStream::query()->sole()))->toBeTrue()
        ->and($post->platform)->toBe(SocialPlatform::BLUESKY)
        ->and($post->status)->toBe(SocialPostStatus::SENT)
        ->and($post->url)->toBe(BLUESKY_POST_URL)
        ->and($post->body)->toContain('Building a castle')
        ->and(internalUpdates()->hasInfoThatContains('Bluesky: posted '.BLUESKY_POST_URL))->toBeTrue();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'createRecord')
        && str_contains($request['record']['text'], 'Playing: Minecraft')
        && $request['record']['embed']['external']['uri'] === 'https://twitch.tv/spacelampsix');
});

it('skips bluesky for a category without a rule', function () {
    enableBluesky($this->user);

    fakeTwitchLiveCheck(helixStream(['game_id' => '1469308723', 'game_name' => 'Software and Game Development']));
    fakeBluesky();

    $this->artisan('twitch:check-live')->assertSuccessful();

    expect(SocialMediaPost::query()->count())->toBe(0)
        ->and(internalUpdates()->hasInfoThatContains('Bluesky: skipped (not enabled for Software and Game Development)'))->toBeTrue();
});

it('skips bluesky when the category rule has it switched off', function () {
    enableBluesky($this->user);
    StreamCategoryRule::factory()->minecraft()->withoutPlatforms()->create();

    fakeTwitchLiveCheck(helixStream());
    fakeBluesky();

    $this->artisan('twitch:check-live')->assertSuccessful();

    expect(SocialMediaPost::query()->count())->toBe(0)
        ->and(internalUpdates()->hasInfoThatContains('Bluesky: skipped (not enabled for Minecraft)'))->toBeTrue();
});

it('skips bluesky when it is not connected', function () {
    SocialPlatform::BLUESKY->setEnabled(true);
    StreamCategoryRule::factory()->minecraft()->create();

    fakeTwitchLiveCheck(helixStream());

    $this->artisan('twitch:check-live')->assertSuccessful();

    expect(SocialMediaPost::query()->count())->toBe(0)
        ->and(internalUpdates()->hasInfoThatContains('Bluesky: skipped (not connected)'))->toBeTrue();
});

it('still announces on discord when bluesky fails', function () {
    enableBluesky($this->user);
    StreamCategoryRule::factory()->minecraft()->create();

    fakeTwitchLiveCheck(helixStream());
    fakeBluesky([BLUESKY_SERVICE.'/xrpc/com.atproto.repo.createRecord' => Http::response(['error' => 'InternalServerError'], 500)]);

    $this->artisan('twitch:check-live')->assertSuccessful();

    $post = SocialMediaPost::query()->sole();

    expect($post->status)->toBe(SocialPostStatus::FAILED)
        ->and($post->error)->toContain('500')
        ->and(LiveStream::query()->sole()->discord_notified_at)->not->toBeNull()
        ->and(internalUpdates()->hasInfoThatContains('Discord notified of live stream successfully.'))->toBeTrue()
        ->and(internalUpdates()->hasInfoThatContains('Bluesky: failed:'))->toBeTrue();
});

/**
 * @param  array<string, mixed>|null  $stream
 * @param  array<string, mixed>  $overrides
 */
function fakeTwitchLiveCheck(?array $stream, array $overrides = []): void
{
    Http::fake($overrides + [
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'fresh-twitch-token']),
        'api.twitch.tv/helix/streams*' => Http::response(['data' => $stream ? [$stream] : []]),
        LIVE_NOW_WEBHOOK => Http::response('', 204),
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function helixStream(array $overrides = []): array
{
    return array_merge([
        'id' => '40001',
        'user_login' => 'spacelampsix',
        'game_id' => '27471',
        'game_name' => 'Minecraft',
        'type' => 'live',
        'title' => 'Building a castle',
        'started_at' => '2026-11-02T18:00:00Z',
        'thumbnail_url' => 'https://static-cdn.jtvnw.net/previews-ttv/live_user_spacelampsix-{width}x{height}.jpg',
    ], $overrides);
}

function enableBluesky(User $user): void
{
    SocialPlatform::BLUESKY->setEnabled(true);

    Connection::factory()->bluesky()->for($user)->create();
}

function internalUpdates(): TestHandler
{
    return Log::channel('discord-internal-updates')->getLogger()->getHandlers()[0];
}
