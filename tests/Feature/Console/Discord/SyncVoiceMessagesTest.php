<?php

use App\Actions\Discord\SyncVoiceMessages;
use App\Models\DiscordChannel;
use App\Models\DiscordVoiceMessage;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const VOICE_CHANNEL_ID = 1398746079767302224;

beforeEach(function () {
    config()->set('services.discord.royalty.beans.bot.token', 'test-bot-token');

    Http::preventStrayRequests();
    Sleep::fake();
});

it('seeds the voice memo channel', function () {
    expect(DiscordChannel::query()->sole())
        ->channel_id->toBe(VOICE_CHANNEL_ID)
        ->name->toBeNull()
        ->backfilled_at->toBeNull();
});

it('reads the whole channel and keeps only the voice messages', function () {
    $messages = collect(range(1, 250))->map(fn (int $i): array => discordMessage(1000 + $i));
    $messages[10] = discordVoiceMessage(1011, 42.7, ['author' => ['id' => '700', 'username' => 'sam', 'global_name' => 'Sam']]);
    $messages[200] = discordVoiceMessage(1201, 8.25);

    fakeDiscordChannel(VOICE_CHANNEL_ID, $messages->all());

    $this->artisan('discord:sync-voice-messages')
        ->expectsOutputToContain('#voice-memos: 2 new, 2 voice messages saved in all.')
        ->assertSuccessful();

    $channel = DiscordChannel::query()->sole();

    expect($channel)
        ->name->toBe('voice-memos')
        ->guild_id->toBe(1100000000000000000)
        ->newest_message_id->toBe(1250)
        ->oldest_message_id->toBe(1001)
        ->backfilled_at->not->toBeNull()
        ->synced_at->not->toBeNull();

    expect(DiscordVoiceMessage::query()->pluck('message_id')->sort()->values()->all())->toBe([1011, 1201]);

    expect(DiscordVoiceMessage::query()->where('message_id', 1011)->sole())
        ->duration_seconds->toBe(42.7)
        ->author_id->toBe(700)
        ->author_name->toBe('Sam')
        ->and(DiscordVoiceMessage::query()->where('message_id', 1011)->sole()->sent_at->toIso8601ZuluString())->toBe('2026-01-01T12:00:00Z');

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bot test-bot-token'));
});

it('only asks for newer messages once the history is in', function () {
    $messages = collect(range(1, 120))->map(fn (int $i): array => discordMessage(1000 + $i));
    $olderPages = fn (): int => Http::recorded(fn (Request $request) => isset($request['before']))->count();

    fakeDiscordChannel(VOICE_CHANNEL_ID, fn (): array => $messages->all());
    $this->artisan('discord:sync-voice-messages')->assertSuccessful();

    expect($olderPages())->toBe(1);

    $messages->push(discordMessage(1121), discordVoiceMessage(1122, 15.0));

    $this->artisan('discord:sync-voice-messages')
        ->expectsOutputToContain('1 new, 1 voice message saved in all.')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => ($request['after'] ?? null) === 1120);
    expect($olderPages())->toBe(1)
        ->and(DiscordChannel::query()->sole()->newest_message_id)->toBe(1122);
});

it('stops at the deadline and picks the backfill back up on the next run', function () {
    $channel = DiscordChannel::query()->sole();

    fakeDiscordChannel(VOICE_CHANNEL_ID, collect(range(1, 250))
        ->map(fn (int $i): array => $i % 10 === 0 ? discordVoiceMessage(1000 + $i, 5.0) : discordMessage(1000 + $i))
        ->all());

    $first = (new SyncVoiceMessages)->handle($channel, now()->subSecond());

    expect($first)->toBe(['new' => 10, 'complete' => false])
        ->and($channel->fresh())
        ->backfilled_at->toBeNull()
        ->oldest_message_id->toBe(1151)
        ->newest_message_id->toBe(1250);

    $second = (new SyncVoiceMessages)->handle($channel->fresh());

    expect($second)->toBe(['new' => 15, 'complete' => true])
        ->and($channel->fresh()->backfilled_at)->not->toBeNull();

    Http::assertSent(fn (Request $request) => ($request['before'] ?? null) === 1151);
});

it('waits out a rate limit and carries on', function () {
    Http::fake([
        'discord.com/api/v10/channels/'.VOICE_CHANNEL_ID => Http::response(['id' => (string) VOICE_CHANNEL_ID, 'name' => 'voice-memos']),
        'discord.com/api/v10/channels/'.VOICE_CHANNEL_ID.'/messages*' => Http::sequence()
            ->push(['message' => 'You are being rate limited.', 'retry_after' => 1.5], 429)
            ->push([discordVoiceMessage(1001, 3.0)], 200, ['X-RateLimit-Remaining' => '0', 'X-RateLimit-Reset-After' => '0.25']),
    ]);

    $this->artisan('discord:sync-voice-messages')->assertSuccessful();

    expect(DiscordVoiceMessage::query()->sole()->duration_seconds)->toBe(3.0);

    Sleep::assertSequence([
        Sleep::usleep(1_600_000),
        Sleep::usleep(250_000),
    ]);
});

it('explains when the bot can not see the channel', function () {
    Http::fake(['discord.com/*' => Http::response(['message' => 'Missing Access', 'code' => 50001], 403)]);

    $this->artisan('discord:sync-voice-messages')
        ->expectsOutputToContain('The bot can not read that channel.')
        ->assertFailed();

    expect(DiscordChannel::query()->sole()->synced_at)->toBeNull();
});

it('needs a token for the channel\'s bot', function () {
    config()->set('services.discord.royalty.beans.bot.token', null);

    $this->artisan('discord:sync-voice-messages')
        ->expectsOutputToContain('No token is set for the royalty.beans bot that reads Channel 1398746079767302224.')
        ->assertFailed();

    Http::assertNothingSent();
});

it('reads each channel with its own bot\'s token', function () {
    config()->set('services.discord.songrank.updates.bot.token', 'songrank-bot-token');

    DiscordChannel::query()->sole()->update(['bot' => 'songrank.updates']);

    fakeDiscordChannel(VOICE_CHANNEL_ID, [discordVoiceMessage(1001, 4.0)]);

    $this->artisan('discord:sync-voice-messages')->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bot songrank-bot-token'));
    Http::assertNotSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bot test-bot-token'));
});

it('starts over with --fresh', function () {
    $channel = DiscordChannel::query()->sole();
    $channel->update(['name' => 'voice-memos', 'newest_message_id' => 1002, 'oldest_message_id' => 1001, 'backfilled_at' => now()]);

    DiscordVoiceMessage::factory()->for($channel, 'channel')->lasting(null)->create(['message_id' => 1001]);

    fakeDiscordChannel(VOICE_CHANNEL_ID, [discordVoiceMessage(1001, 9.0), discordMessage(1002)]);

    $this->artisan('discord:sync-voice-messages', ['--fresh' => true])->assertSuccessful();

    expect(DiscordVoiceMessage::query()->sole()->duration_seconds)->toBe(9.0);
});

/**
 * Serves a channel the way Discord does: 100 at a time, newest first, with
 * before and after picking the messages right next to the cursor. Pass a
 * closure to add messages between syncs.
 *
 * @param  list<array<string, mixed>>|Closure(): list<array<string, mixed>>  $messages
 */
function fakeDiscordChannel(int $channelId, array|Closure $messages): void
{
    Http::fake([
        "discord.com/api/v10/channels/{$channelId}/messages*" => function (Request $request) use ($messages) {
            $byId = collect(value($messages))->sortBy(fn (array $message): int => (int) $message['id'])->values();
            $limit = (int) ($request['limit'] ?? 50);

            $page = match (true) {
                isset($request['after']) => $byId->filter(fn (array $m): bool => (int) $m['id'] > (int) $request['after'])->take($limit),
                isset($request['before']) => $byId->filter(fn (array $m): bool => (int) $m['id'] < (int) $request['before'])->take(-$limit),
                default => $byId->take(-$limit),
            };

            return Http::response($page->reverse()->values()->all());
        },
        "discord.com/api/v10/channels/{$channelId}" => Http::response([
            'id' => (string) $channelId,
            'name' => 'voice-memos',
            'guild_id' => '1100000000000000000',
        ]),
    ]);
}

/**
 * @return array<string, mixed>
 */
function discordMessage(int $id, array $overrides = []): array
{
    return array_replace([
        'id' => (string) $id,
        'type' => 0,
        'flags' => 0,
        'timestamp' => '2026-01-01T12:00:00.000000+00:00',
        'author' => ['id' => '500', 'username' => 'kyle', 'global_name' => 'Kyle'],
        'attachments' => [],
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function discordVoiceMessage(int $id, float $seconds, array $overrides = []): array
{
    return discordMessage($id, [
        'flags' => 8192,
        'attachments' => [[
            'id' => (string) ($id + 1),
            'filename' => 'voice-message.ogg',
            'content_type' => 'audio/ogg',
            'duration_secs' => $seconds,
            'waveform' => 'AAAA',
        ]],
        ...$overrides,
    ]);
}
