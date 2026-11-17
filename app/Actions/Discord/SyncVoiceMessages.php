<?php

namespace App\Actions\Discord;

use App\Actions\Api\Discord\GetChannel;
use App\Actions\Api\Discord\GetChannelMessages;
use App\Models\DiscordChannel;
use App\Models\DiscordVoiceMessage;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use RuntimeException;

final class SyncVoiceMessages
{
    /**
     * Discord can not be asked for voice messages alone, so every message is read
     * a page at a time and only the voice ones are kept. New messages come first,
     * then older history back to the start of the channel. A deadline stops
     * between pages so the stats page can sync in chunks, and the message ids
     * saved on the channel let the next run carry on from there. Each channel is
     * read with its own bot's token.
     *
     * @return array{new: int, complete: bool}
     */
    public function handle(DiscordChannel $channel, ?CarbonInterface $deadline = null): array
    {
        $token = $channel->botToken() ?? throw new RuntimeException("No token is set for the {$channel->bot} bot.");

        if ($channel->name === null) {
            $this->describe($channel, new GetChannel($token));
        }

        $countBefore = $channel->voiceMessages()->count();

        $messages = new GetChannelMessages($token);

        $complete = $this->pullNewer($channel, $messages, $deadline) && $this->pullOlder($channel, $messages, $deadline);

        $channel->update(['synced_at' => now()]);

        return [
            'new' => $channel->voiceMessages()->count() - $countBefore,
            'complete' => $complete,
        ];
    }

    private function describe(DiscordChannel $channel, GetChannel $channels): void
    {
        $details = $channels->handle($channel->channel_id);

        $channel->update([
            'name' => $details['name'] ?? null,
            'guild_id' => isset($details['guild_id']) ? (int) $details['guild_id'] : null,
        ]);
    }

    /**
     * A channel that has never been read skips this, since the backfill starts from the newest message.
     */
    private function pullNewer(DiscordChannel $channel, GetChannelMessages $messages, ?CarbonInterface $deadline): bool
    {
        if ($channel->newest_message_id === null) {
            return true;
        }

        do {
            $page = $messages->handle($channel->channel_id, ['after' => $channel->newest_message_id]);

            $this->store($channel, $page);

            if ($page !== []) {
                $channel->update(['newest_message_id' => $this->ids($page)->max()]);
            }

            $hasMore = count($page) === GetChannelMessages::PAGE_SIZE;
        } while ($hasMore && ! $this->outOfTime($deadline));

        return ! $hasMore;
    }

    private function pullOlder(DiscordChannel $channel, GetChannelMessages $messages, ?CarbonInterface $deadline): bool
    {
        if ($channel->backfilled_at) {
            return true;
        }

        do {
            $page = $messages->handle(
                $channel->channel_id,
                $channel->oldest_message_id ? ['before' => $channel->oldest_message_id] : [],
            );

            $this->store($channel, $page);

            if ($page !== []) {
                $channel->update([
                    'oldest_message_id' => $this->ids($page)->min(),
                    'newest_message_id' => $channel->newest_message_id ?? $this->ids($page)->max(),
                ]);
            }

            $hasMore = count($page) === GetChannelMessages::PAGE_SIZE;
        } while ($hasMore && ! $this->outOfTime($deadline));

        if ($hasMore) {
            return false;
        }

        $channel->update(['backfilled_at' => now()]);

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $page
     */
    private function store(DiscordChannel $channel, array $page): void
    {
        $rows = collect($page)
            ->filter(fn (array $message): bool => ((int) ($message['flags'] ?? 0) & DiscordVoiceMessage::FLAG) !== 0)
            ->map(fn (array $message): array => $this->toRow($channel, $message))
            ->values()
            ->all();

        if ($rows === []) {
            return;
        }

        DiscordVoiceMessage::upsert(
            $rows,
            ['message_id'],
            ['author_username', 'author_display_name', 'duration_seconds'],
        );
    }

    /**
     * The length lives on the message's single audio attachment, which Discord
     * only sends when the bot has the Message Content intent.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private function toRow(DiscordChannel $channel, array $message): array
    {
        $duration = collect($message['attachments'] ?? [])
            ->pluck('duration_secs')
            ->first(fn (mixed $seconds): bool => $seconds !== null);

        return [
            'discord_channel_id' => $channel->id,
            'message_id' => (int) $message['id'],
            'author_id' => (int) $message['author']['id'],
            'author_username' => $message['author']['username'],
            'author_display_name' => $message['author']['global_name'] ?? null,
            'duration_seconds' => $duration !== null ? round((float) $duration, 2) : null,
            'sent_at' => Carbon::parse($message['timestamp'])->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $page
     * @return Collection<int, int>
     */
    private function ids(array $page): Collection
    {
        return collect($page)->map(fn (array $message): int => (int) $message['id']);
    }

    private function outOfTime(?CarbonInterface $deadline): bool
    {
        return $deadline !== null && $deadline->isPast();
    }
}
