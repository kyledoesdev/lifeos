<?php

use App\Actions\LiveStreams\ShareLiveStream;
use App\Actions\Social\PublishSocialMediaPost;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostStatus;
use App\Models\Connection;
use App\Models\LiveStream;
use App\Models\SocialMediaPost;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.twitch.channel', 'spacelampsix');

    Http::preventStrayRequests();

    fakeBluesky();

    $this->user = User::factory()->create();

    Connection::factory()->bluesky()->for($this->user)->create();
});

it('fills the template with the stream details', function () {
    SocialPlatform::BLUESKY->setLiveStreamTemplate('{category} time! {title} {url}');

    $stream = LiveStream::factory()->create(['title' => 'Building a castle']);

    $post = (new ShareLiveStream)->shareTo($stream, SocialPlatform::BLUESKY, $this->user);

    expect($post->body)->toBe('Minecraft time! Building a castle https://twitch.tv/spacelampsix');
});

it('shortens a long title to fit the character limit', function () {
    $stream = LiveStream::factory()->create(['title' => str_repeat('🧱 castle ', 60)]);

    $post = (new ShareLiveStream)->shareTo($stream, SocialPlatform::BLUESKY, $this->user);

    expect(mb_strlen($post->body))->toBeLessThanOrEqual(SocialPlatform::BLUESKY->characterLimit())
        ->and($post->body)->toContain('…')
        ->and($post->body)->toEndWith('https://twitch.tv/spacelampsix');
});

it('never posts the same model to a platform twice', function () {
    $stream = LiveStream::factory()->create();

    SocialMediaPost::factory()->for($stream, 'postable')->create();

    $post = (new PublishSocialMediaPost)->handle($this->user, $stream, [
        'platform' => SocialPlatform::BLUESKY,
        'body' => 'Hello again',
    ]);

    expect($post->body)->not->toBe('Hello again')
        ->and(SocialMediaPost::query()->count())->toBe(1);

    Http::assertNothingSent();
});

it('retries a failed post in place', function () {
    $stream = LiveStream::factory()->create();

    SocialMediaPost::factory()->failed()->for($stream, 'postable')->create();

    $post = (new PublishSocialMediaPost)->handle($this->user, $stream, [
        'platform' => SocialPlatform::BLUESKY,
        'body' => 'Second try',
    ]);

    expect($post->status)->toBe(SocialPostStatus::SENT)
        ->and($post->error)->toBeNull()
        ->and($post->url)->toBe(BLUESKY_POST_URL)
        ->and(SocialMediaPost::query()->count())->toBe(1);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'createRecord')
        && $request['record']['text'] === 'Second try');
});
