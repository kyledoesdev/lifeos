<?php

namespace App\Console\Commands\LiveStreams;

use App\Actions\Api\Twitch\GetLiveStream;
use App\Actions\Api\Twitch\RefreshToken;
use App\Actions\LiveStreams\ShareLiveStream;
use App\Enums\ConnectionType;
use App\Models\LiveStream;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckTwitchLiveStatus extends Command
{
    protected $signature = 'twitch:check-live';

    protected $description = 'Check if the Twitch channel is live, notify Discord, and share it to social media';

    public function handle(): int
    {
        $user = User::first();

        (new RefreshToken)->handle($user);

        $token = $user->connections->firstWhere('type', ConnectionType::TWITCH->value)->token ?? null;

        if (! $token) {
            return self::FAILURE;
        }

        try {
            $stream = (new GetLiveStream)->handle($token);
        } catch (ConnectionException|RequestException $e) {
            $this->error("Could not fetch the live stream from Twitch: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! $stream) {
            $this->closeOpenStreams();

            $this->info(config('services.twitch.channel').' is not currently live.');

            return self::SUCCESS;
        }

        $liveStream = LiveStream::query()->firstOrCreate(['external_id' => $stream['id']], [
            'title' => $stream['title'] ?: null,
            'category_id' => $stream['game_id'] ?: null,
            'category_name' => $stream['game_name'] ?: null,
            'started_at' => Carbon::parse($stream['started_at'])->setTimezone(config('app.timezone')),
        ]);

        if (! $liveStream->wasRecentlyCreated) {
            $this->info('Already notified for this stream session.');

            return self::SUCCESS;
        }

        $discordNotified = $this->notifyDiscord($liveStream);

        $summary = collect((new ShareLiveStream)->handle($liveStream, $user))
            ->map(fn (string $outcome, string $platform): string => "{$platform}: {$outcome}")
            ->when($discordNotified, fn (Collection $lines) => $lines->prepend('Discord notified of live stream successfully. Have a great stream!'))
            ->implode("\n");

        Log::channel('discord-internal-updates')->info($summary);

        $this->info($summary);

        return self::SUCCESS;
    }

    private function closeOpenStreams(): void
    {
        LiveStream::query()
            ->whereNotNull('external_id')
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);
    }

    private function notifyDiscord(LiveStream $stream): bool
    {
        try {
            $response = Http::post(config('services.discord.song-rank.live-now.webhook_url'), [
                'content' => '@everyone 🔴 **'.config('services.twitch.channel')." is LIVE!**\n\n**{$stream->title_label}**\nPlaying: {$stream->category_label}\n\n{$stream->url()}",
                'allowed_mentions' => [
                    'parse' => ['everyone'],
                ],
            ]);
        } catch (ConnectionException) {
            return false;
        }

        if ($response->successful()) {
            $stream->update(['discord_notified_at' => now()]);
        }

        return $response->successful();
    }
}
