<?php

use App\Enums\SocialPlatform;
use App\Livewire\Pages\LiveStreams\Edit;
use App\Models\Connection;
use App\Models\LiveStream;
use App\Models\SocialMediaPost;
use App\Models\StreamCategoryRule;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();

    $this->user = User::factory()->create();

    $this->actingAs($this->user);
});

it('gates the live streams page behind auth', function () {
    auth()->logout();

    $this->get(route('live_streams.edit'))->assertRedirect(route('login'));
});

it('loads the page and links from the dashboard', function () {
    LiveStream::factory()->create(['title' => 'Building a castle']);

    $this->get(route('live_streams.edit'))
        ->assertOk()
        ->assertSee('Building a castle');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('live_streams.edit'));
});

it('renders every tab', function () {
    Livewire::test(Edit::class)
        ->assertSee('Stream History')
        ->assertSee('Go live post')
        ->assertSee('Category Rules');
});

it('lists streams newest first with their posts', function () {
    $older = LiveStream::factory()->ended()->create(['started_at' => now()->subDays(3), 'title' => 'Older stream']);
    LiveStream::factory()->ended()->create(['started_at' => now()->subDay(), 'title' => 'Newer stream']);

    SocialMediaPost::factory()->for($older, 'postable')->create(['url' => BLUESKY_POST_URL]);

    $component = Livewire::test(Edit::class)
        ->assertSee(BLUESKY_POST_URL)
        ->assertSee('3 hours')
        ->assertDontSee('Post now');

    expect($component->instance()->streams()->pluck('title')->all())->toBe(['Newer stream', 'Older stream']);
});

it('filters the history by title or category', function () {
    LiveStream::factory()->create(['title' => 'Building a castle']);
    LiveStream::factory()->softwareDevelopment()->create(['title' => 'Writing tests']);

    $component = Livewire::test(Edit::class);

    expect($component->set('search', 'castle')->instance()->streams()->pluck('title')->all())->toBe(['Building a castle'])
        ->and($component->set('search', 'Software')->instance()->streams()->pluck('title')->all())->toBe(['Writing tests']);
});

it('posts a live stream to bluesky on demand', function () {
    fakeBluesky();

    Connection::factory()->bluesky()->for($this->user)->create();

    $stream = LiveStream::factory()->create();

    Livewire::test(Edit::class)
        ->assertSee('Post now')
        ->call('postNow', $stream->id, 'bluesky')
        ->assertSee(BLUESKY_POST_URL)
        ->assertDontSee('Post now');

    expect($stream->socialMediaPosts()->sole()->wasSent())->toBeTrue();
});

it('retries a failed post', function () {
    fakeBluesky();

    Connection::factory()->bluesky()->for($this->user)->create();

    $stream = LiveStream::factory()->create();

    SocialMediaPost::factory()->failed()->for($stream, 'postable')->create();

    Livewire::test(Edit::class)
        ->assertSee('Retry')
        ->call('postNow', $stream->id, 'bluesky');

    expect($stream->socialMediaPosts()->sole()->wasSent())->toBeTrue();
});

it('will not share a stream that has ended', function () {
    Connection::factory()->bluesky()->for($this->user)->create();

    $stream = LiveStream::factory()->ended()->create();

    Livewire::test(Edit::class)->call('postNow', $stream->id, 'bluesky');

    expect(SocialMediaPost::query()->count())->toBe(0);

    Http::assertNothingSent();
});

it('will not share to a platform that is not connected', function () {
    $stream = LiveStream::factory()->create();

    Livewire::test(Edit::class)->call('postNow', $stream->id, 'bluesky');

    expect(SocialMediaPost::query()->count())->toBe(0);

    Http::assertNothingSent();
});

it('rejects an unknown platform', function () {
    $stream = LiveStream::factory()->create();
    $rule = StreamCategoryRule::factory()->minecraft()->create();

    Livewire::test(Edit::class)
        ->call('postNow', $stream->id, 'myspace')
        ->assertStatus(422);

    Livewire::test(Edit::class)
        ->call('togglePlatform', $rule->id, 'myspace')
        ->assertStatus(422);
});

it('persists the platform switch to the cache store', function () {
    Livewire::test(Edit::class)
        ->assertSet('enabled.bluesky', false)
        ->set('enabled.bluesky', true);

    expect(SocialPlatform::BLUESKY->enabled())->toBeTrue();

    Livewire::test(Edit::class)
        ->assertSet('enabled.bluesky', true)
        ->set('enabled.bluesky', false);

    expect(SocialPlatform::BLUESKY->enabled())->toBeFalse();
});

it('links to the connections page when a platform is not connected', function () {
    Livewire::test(Edit::class)
        ->assertSee('Not connected')
        ->assertSee(route('connections.edit'));
});

it('shows a connected platform', function () {
    Connection::factory()->bluesky()->for($this->user)->create();

    Livewire::test(Edit::class)->assertDontSee('Not connected');
});

it('saves a custom template', function () {
    $default = SocialPlatform::BLUESKY->liveStreamTemplate();

    Livewire::test(Edit::class)
        ->assertSet('templateForm.templates.bluesky', $default)
        ->set('templateForm.templates.bluesky', 'Live with {category}! {url}')
        ->call('saveTemplate', 'bluesky')
        ->assertHasNoErrors();

    expect(SocialPlatform::BLUESKY->liveStreamTemplate())->toBe('Live with {category}! {url}');
});

it('rejects a template that is empty or too long', function () {
    $default = SocialPlatform::BLUESKY->liveStreamTemplate();

    Livewire::test(Edit::class)
        ->set('templateForm.templates.bluesky', '')
        ->call('saveTemplate', 'bluesky')
        ->assertHasErrors(['templateForm.templates.bluesky' => 'required'])
        ->set('templateForm.templates.bluesky', str_repeat('a', 301))
        ->call('saveTemplate', 'bluesky')
        ->assertHasErrors(['templateForm.templates.bluesky' => 'max']);

    expect(SocialPlatform::BLUESKY->liveStreamTemplate())->toBe($default);
});

it('resets a template to the default', function () {
    $default = SocialPlatform::BLUESKY->liveStreamTemplate();

    SocialPlatform::BLUESKY->setLiveStreamTemplate('Custom {url}');

    Livewire::test(Edit::class)
        ->assertSet('templateForm.templates.bluesky', 'Custom {url}')
        ->call('resetTemplate', 'bluesky')
        ->assertSet('templateForm.templates.bluesky', $default);

    expect(SocialPlatform::BLUESKY->liveStreamTemplate())->toBe($default);
});

it('searches twitch and adds a rule for a category', function () {
    fakeTwitchCategorySearch($this->user);

    Livewire::test(Edit::class)
        ->set('phrase', 'minecraft')
        ->call('searchCategories')
        ->assertCount('searchedCategories', 2)
        ->call('selectCategory', 0)
        ->assertSet('ruleForm.category_id', '27471')
        ->assertSet('ruleForm.category_name', 'Minecraft')
        ->assertSet('ruleForm.box_art_url', 'https://static-cdn.jtvnw.net/ttv-boxart/27471-285x380.jpg')
        ->assertSet('searchedCategories', [])
        ->set('ruleForm.platforms', ['bluesky'])
        ->call('storeRule')
        ->assertHasNoErrors()
        ->assertSet('ruleForm.category_id', '')
        ->assertSee('Minecraft');

    $rule = StreamCategoryRule::query()->sole();

    expect($rule->category_id)->toBe('27471')
        ->and($rule->allows(SocialPlatform::BLUESKY))->toBeTrue();
});

it('adds a rule that never posts when no platform is chosen', function () {
    fakeTwitchCategorySearch($this->user);

    Livewire::test(Edit::class)
        ->set('phrase', 'minecraft')
        ->call('searchCategories')
        ->call('selectCategory', 0)
        ->call('storeRule')
        ->assertHasNoErrors();

    expect(StreamCategoryRule::query()->sole()->platforms)->toBeEmpty();
});

it('hides categories that already have a rule from the search', function () {
    fakeTwitchCategorySearch($this->user);

    StreamCategoryRule::factory()->minecraft()->create();

    Livewire::test(Edit::class)
        ->set('phrase', 'minecraft')
        ->call('searchCategories')
        ->assertCount('searchedCategories', 1)
        ->assertSet('searchedCategories.0.name', 'Software and Game Development');
});

it('ignores a selection that was not in the returned list', function () {
    fakeTwitchCategorySearch($this->user);

    Livewire::test(Edit::class)
        ->set('phrase', 'minecraft')
        ->call('searchCategories')
        ->call('selectCategory', 99)
        ->assertSet('ruleForm.category_id', '');
});

it('rejects a platform the life os can not post to', function () {
    fakeTwitchCategorySearch($this->user);

    Livewire::test(Edit::class)
        ->set('phrase', 'minecraft')
        ->call('searchCategories')
        ->call('selectCategory', 0)
        ->set('ruleForm.platforms', ['myspace'])
        ->call('storeRule')
        ->assertHasErrors('ruleForm.platforms.0');

    expect(StreamCategoryRule::query()->count())->toBe(0);
});

it('toggles a platform on a rule', function () {
    $rule = StreamCategoryRule::factory()->minecraft()->create();

    $component = Livewire::test(Edit::class)->call('togglePlatform', $rule->id, 'bluesky');

    expect($rule->fresh()->allows(SocialPlatform::BLUESKY))->toBeFalse();

    $component->call('togglePlatform', $rule->id, 'bluesky');

    expect($rule->fresh()->allows(SocialPlatform::BLUESKY))->toBeTrue();
});

it('deletes a rule', function () {
    $rule = StreamCategoryRule::factory()->minecraft()->create();

    Livewire::test(Edit::class)
        ->call('destroyRule', $rule->id)
        ->assertOk();

    expect(StreamCategoryRule::query()->count())->toBe(0);
});

function fakeTwitchCategorySearch(User $user): void
{
    Connection::factory()->twitch()->for($user)->create();

    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'fresh-twitch-token']),
        'api.twitch.tv/helix/search/categories*' => Http::response(['data' => [
            twitchCategory('27471', 'Minecraft'),
            twitchCategory('1469308723', 'Software and Game Development'),
        ]]),
    ]);
}

/**
 * @return array{id: string, name: string, box_art_url: string}
 */
function twitchCategory(string $id, string $name): array
{
    return [
        'id' => $id,
        'name' => $name,
        'box_art_url' => "https://static-cdn.jtvnw.net/ttv-boxart/{$id}-52x72.jpg",
    ];
}
