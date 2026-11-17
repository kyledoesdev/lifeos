<?php

namespace App\Livewire\Pages\Discord\BeansStats;

use App\Actions\Api\Discord\DescribeBotFailure;
use App\Actions\Discord\BuildVoiceMessageStats;
use App\Actions\Discord\SyncVoiceMessages;
use App\Models\DiscordChannel;
use Carbon\CarbonInterval;
use Flux\Flux;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    /**
     * A web request syncs for about this long, then hands back what it has. The
     * first full read of the channel belongs to `php artisan discord:sync-voice-messages`.
     */
    private const int SYNC_SECONDS = 15;

    public string $period = 'month';

    public function render()
    {
        return view('livewire.pages.discord.beans-stats.show');
    }

    /**
     * Only the voice memo channel seeded with the table is tracked for now.
     */
    #[Computed]
    public function channel(): ?DiscordChannel
    {
        return DiscordChannel::query()->oldest('id')->first();
    }

    #[Computed]
    public function stats(): ?array
    {
        return $this->channel ? (new BuildVoiceMessageStats)->handle($this->channel, $this->period) : null;
    }

    #[Computed]
    public function hasBotToken(): bool
    {
        return filled($this->channel?->botToken());
    }

    public function updatedPeriod(string $period): void
    {
        if (! in_array($period, ['week', 'month'], true)) {
            $this->period = 'month';
        }
    }

    public function sync(): void
    {
        $channel = $this->channel ?? abort(404);

        if (! $this->hasBotToken) {
            Flux::toast(variant: 'danger', text: 'Add the bot token to .env first.', duration: 6000);

            return;
        }

        try {
            $result = (new SyncVoiceMessages)->handle($channel, now()->addSeconds(self::SYNC_SECONDS));
        } catch (ConnectionException|RequestException $e) {
            Flux::toast(variant: 'danger', text: (new DescribeBotFailure)->handle($e), duration: 6000);

            return;
        }

        unset($this->channel, $this->stats);

        $found = number_format($result['new']).' new voice '.str('message')->plural($result['new']);

        Flux::toast(
            variant: 'success',
            text: $result['complete']
                ? "Up to date. Found {$found}."
                : "Found {$found}. Older history is still left, so press Sync again to keep going.",
            duration: 6000,
        );
    }

    public function duration(float $seconds): string
    {
        if ($seconds < 1) {
            return '0s';
        }

        return CarbonInterval::seconds((int) round($seconds))->cascade()->forHumans(['short' => true, 'parts' => 2]);
    }
}
