<?php

namespace App\Console\Commands\Discord;

use App\Actions\Api\Discord\DescribeBotFailure;
use App\Actions\Discord\SyncVoiceMessages as SyncChannelVoiceMessages;
use App\Models\DiscordChannel;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class SyncVoiceMessages extends Command
{
    protected $signature = 'discord:sync-voice-messages
        {--fresh : Forget the saved voice messages and read the whole channel again}';

    protected $description = 'Read the tracked Discord channels with the bot and save their voice messages';

    public function handle(): int
    {
        $channels = DiscordChannel::query()->oldest('id')->get();

        if ($channels->isEmpty()) {
            $this->warn('No Discord channels are tracked.');

            return self::SUCCESS;
        }

        foreach ($channels as $channel) {
            if (! $channel->botToken()) {
                $this->error("No token is set for the {$channel->bot} bot that reads {$channel->label}. Add it to .env first.");

                return self::FAILURE;
            }

            if ($this->option('fresh')) {
                $channel->voiceMessages()->delete();
                $channel->update(['newest_message_id' => null, 'oldest_message_id' => null, 'backfilled_at' => null]);
            }

            $this->info("Syncing {$channel->label}...");

            try {
                $result = (new SyncChannelVoiceMessages)->handle($channel);
            } catch (ConnectionException|RequestException $e) {
                $this->error((new DescribeBotFailure)->handle($e));

                return self::FAILURE;
            }

            $saved = $channel->voiceMessages()->count();

            $this->line(sprintf(
                '%s: %s new, %s voice %s saved in all.',
                $channel->label,
                number_format($result['new']),
                number_format($saved),
                str('message')->plural($saved),
            ));
        }

        return self::SUCCESS;
    }
}
