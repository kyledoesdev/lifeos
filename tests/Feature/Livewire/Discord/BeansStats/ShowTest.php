<?php

use App\Livewire\Pages\Discord\BeansStats\Show;
use App\Models\DiscordChannel;
use App\Models\DiscordVoiceMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('services.discord.royalty.beans.bot.token', 'test-bot-token');

    Http::preventStrayRequests();

    $this->actingAs(User::factory()->create());

    $this->channel = DiscordChannel::query()->sole();
});

it('gates the beans stats page behind auth', function () {
    auth()->logout();

    $this->get(route('beans_stats'))->assertRedirect(route('login'));
});

it('loads the seeded channel and links from the dashboard', function () {
    expect(route('beans_stats', absolute: false))->toBe('/beans');

    $this->get(route('beans_stats'))
        ->assertOk()
        ->assertSee('Channel 1398746079767302224')
        ->assertSee('No voice messages saved yet.');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('beans_stats'))
        ->assertSee('Royal Tea Stats');
});

it('shows who sends voice messages', function () {
    $this->channel->update(['name' => 'voice-memos']);

    DiscordVoiceMessage::factory()->for($this->channel, 'channel')->by(1, 'sam', 'Sam')->lasting(125.0)->create();
    DiscordVoiceMessage::factory()->for($this->channel, 'channel')->by(1, 'sam', 'Sam')->lasting(5.0)->create();
    DiscordVoiceMessage::factory()->for($this->channel, 'channel')->by(2, 'riley', 'Riley')->lasting(30.0)->create();

    $this->get(route('beans_stats'))
        ->assertOk()
        ->assertSee('#voice-memos')
        ->assertSeeInOrder(['Most voice messages', 'Sam', 'Riley'])
        ->assertSee('2m 40s')
        ->assertSee('from 2 people');
});

it('pulls new voice messages when synced', function () {
    $this->channel->update(['name' => 'voice-memos', 'newest_message_id' => 1001, 'oldest_message_id' => 1001, 'backfilled_at' => now()]);

    Http::fake(["discord.com/api/v10/channels/{$this->channel->channel_id}/messages*" => Http::response([
        [
            'id' => '1003',
            'flags' => 8192,
            'timestamp' => '2026-11-16T20:00:00+00:00',
            'author' => ['id' => '7', 'username' => 'sam', 'global_name' => 'Sam'],
            'attachments' => [['id' => '1', 'filename' => 'voice-message.ogg', 'duration_secs' => 11.2]],
        ],
        [
            'id' => '1002',
            'flags' => 0,
            'timestamp' => '2026-11-16T19:59:00+00:00',
            'author' => ['id' => '8', 'username' => 'riley', 'global_name' => 'Riley'],
            'attachments' => [],
        ],
    ])]);

    Livewire::test(Show::class)
        ->call('sync')
        ->assertSee('Sam')
        ->assertDontSee('Riley');

    expect($this->channel->voiceMessages()->count())->toBe(1)
        ->and($this->channel->fresh())
        ->newest_message_id->toBe(1003)
        ->synced_at->not->toBeNull();
});

it('warns when voice messages have no length', function () {
    DiscordVoiceMessage::factory()->for($this->channel, 'channel')->lasting(null)->create();

    $this->get(route('beans_stats'))->assertSee('came back without a length');
});

it('asks for a bot token when there is none', function () {
    config()->set('services.discord.royalty.beans.bot.token', null);

    $this->get(route('beans_stats'))
        ->assertOk()
        ->assertSee('Add a Discord bot token');
});
