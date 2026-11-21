<?php

use App\Actions\LiveStreams\ShareLiveStream;
use App\Actions\Social\PublishSocialMediaPost;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostStatus;
use App\Models\Connection;
use App\Models\LiveStream;
use App\Models\SocialMediaPost;
use App\Models\StreamCategoryRule;
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
    SocialPlatform::BLUESKY->settings()->fill(['live_stream_template' => '{category} time! {title} {url}'])->save();

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

it('uses the category template over the platform template', function () {
    SocialPlatform::BLUESKY->settings()->fill(['enabled' => true, 'live_stream_template' => 'Platform {url}'])->save();
    StreamCategoryRule::factory()->minecraft()->withTemplate('Mining! {title} {url}')->create();

    (new ShareLiveStream)->handle(LiveStream::factory()->create(['title' => 'Castle']), $this->user);

    expect(SocialMediaPost::query()->sole()->body)->toBe('Mining! Castle https://twitch.tv/spacelampsix');
});

it('falls back to the platform template when the category has none', function () {
    SocialPlatform::BLUESKY->settings()->fill(['live_stream_template' => 'Platform {title}'])->save();
    StreamCategoryRule::factory()->minecraft()->create();

    $post = (new ShareLiveStream)->shareTo(LiveStream::factory()->create(['title' => 'Castle']), SocialPlatform::BLUESKY, $this->user);

    expect($post->body)->toBe('Platform Castle');
});

it('falls back to the built in default when nothing is customized', function () {
    $post = (new ShareLiveStream)->shareTo(LiveStream::factory()->create(['title' => 'Castle']), SocialPlatform::BLUESKY, $this->user);

    expect($post->body)->toBe(strtr(SocialPlatform::BLUESKY->defaultLiveStreamTemplate(), [
        '{title}' => 'Castle',
        '{category}' => 'Minecraft',
        '{url}' => 'https://twitch.tv/spacelampsix',
    ]));
});

it('uses the category template when posting on demand', function () {
    StreamCategoryRule::factory()->minecraft()->withoutPlatforms()->withTemplate('Mining! {url}')->create();

    $post = (new ShareLiveStream)->shareTo(LiveStream::factory()->create(), SocialPlatform::BLUESKY, $this->user);

    expect($post->body)->toBe('Mining! https://twitch.tv/spacelampsix');
});

it('shortens a long title to fit a category template', function () {
    StreamCategoryRule::factory()->minecraft()->withTemplate(str_repeat('⛏️', 100).' {title} {url}')->create();

    $post = (new ShareLiveStream)->shareTo(LiveStream::factory()->create(['title' => str_repeat('castle ', 60)]), SocialPlatform::BLUESKY, $this->user);

    expect(mb_strlen($post->body))->toBeLessThanOrEqual(SocialPlatform::BLUESKY->characterLimit())
        ->and($post->body)->toStartWith('⛏️')
        ->and($post->body)->toContain('…');
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
