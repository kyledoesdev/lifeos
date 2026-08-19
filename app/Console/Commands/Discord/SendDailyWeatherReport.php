<?php

namespace App\Console\Commands\Discord;

use App\Actions\Api\Weather\GetDailyForecast;
use App\Enums\Discord\PingBeansRole;
use App\Enums\WeatherCondition;
use App\Models\WeatherReportCity;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendDailyWeatherReport extends Command
{
    protected $signature = 'royal-tea:weather-report';

    protected $description = 'Send the daily weather report to Discord';

    /**
     * Discord trims trailing whitespace from a field, so the blank line between
     * cities is held open by a zero width space.
     */
    private const string BLANK_LINE = "\n\u{200B}";

    public function handle(): int
    {
        $cities = WeatherReportCity::query()
            ->get()
            ->map(fn (WeatherReportCity $city): array => $city->toForecastLocation())
            ->all();

        if ($cities === []) {
            $this->reportFailure('No weather report cities have been configured.');

            return self::FAILURE;
        }

        $forecasts = (new GetDailyForecast)->handle($cities);

        if ($forecasts->isEmpty()) {
            $this->reportFailure('Failed to retrieve the daily weather report.', [
                'cities' => count($cities),
            ]);

            return self::FAILURE;
        }

        try {
            $response = Http::timeout(15)
                ->retry(3, 2000, throw: false)
                ->post(
                    config('services.discord.royalty.weather-report.webhook_url'),
                    $this->buildPayload($forecasts)
                );
        } catch (ConnectionException $e) {
            $this->reportFailure('Could not reach Discord to send the daily weather report.', [
                'message' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        if ($response->failed()) {
            $this->reportFailure('Failed to send the daily weather report to Discord.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return self::FAILURE;
        }

        $this->info("Daily weather report sent for {$forecasts->count()} of ".count($cities).' cities.');

        return self::SUCCESS;
    }

    /**
     * A mention only notifies from the top level content, never from inside an embed.
     *
     * @param  Collection<int, array{name: string, condition: WeatherCondition, high: int, low: int, feels_like: int, chance_of_rain: int, precipitation: float, wind_speed: int, sunrise: ?string, sunset: ?string}>  $forecasts
     * @return array<string, mixed>
     */
    private function buildPayload(Collection $forecasts): array
    {
        $payload = ['embeds' => [$this->buildEmbed($forecasts)]];

        $roleId = config('services.discord.royalty.roles.beans');

        if (! $roleId || ! PingBeansRole::WEATHER_REPORT->enabled()) {
            return $payload;
        }

        return $payload + [
            'content' => "<@&{$roleId}>",
            'allowed_mentions' => ['roles' => [(string) $roleId]],
        ];
    }

    /**
     * @param  Collection<int, array{name: string, condition: WeatherCondition, high: int, low: int, feels_like: int, chance_of_rain: int, precipitation: float, wind_speed: int, sunrise: ?string, sunset: ?string}>  $forecasts
     * @return array<string, mixed>
     */
    private function buildEmbed(Collection $forecasts): array
    {
        $lastIndex = $forecasts->count() - 1;

        return [
            'title' => '🌤️  Good Morning! Here is Your Daily Weather Report',
            'description' => now()->format('l, F jS, Y'),
            'color' => $forecasts->first()['condition']->color(),
            'fields' => $forecasts->values()->map(fn (array $forecast, int $index): array => [
                'name' => "{$forecast['condition']->emoji()}  {$forecast['name']}",
                'value' => $this->buildFieldValue($forecast).($index === $lastIndex ? '' : self::BLANK_LINE),
                'inline' => false,
            ])->all(),
            'footer' => ['text' => 'Powered by kyledoes.dev'],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array{name: string, condition: WeatherCondition, high: int, low: int, feels_like: int, chance_of_rain: int, precipitation: float, wind_speed: int, sunrise: ?string, sunset: ?string}  $forecast
     */
    private function buildFieldValue(array $forecast): string
    {
        $conditions = "💧 {$forecast['chance_of_rain']}% · 💨 {$forecast['wind_speed']} mph";

        if ($forecast['precipitation'] > 0) {
            $conditions .= " · 🌂 {$forecast['precipitation']}\" expected";
        }

        if ($forecast['sunrise'] && $forecast['sunset']) {
            $conditions .= " · 🌅 {$forecast['sunrise']} · 🌇 {$forecast['sunset']}";
        }

        return implode("\n", [
            "**{$forecast['high']}° / {$forecast['low']}°** · {$forecast['condition']->label()} · Feels like {$forecast['feels_like']}°",
            $conditions,
        ]);
    }

    private function reportFailure(string $message, array $context = []): void
    {
        $this->error($message);

        Log::error("[royal-tea:weather-report] {$message}", $context);
    }
}
