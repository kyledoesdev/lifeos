<?php

namespace App\Actions\Api\Weather;

use App\Enums\WeatherCondition;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GetDailyForecast
{
    private const string ENDPOINT = 'https://api.open-meteo.com/v1/forecast';

    private const array DAILY_METRICS = [
        'weather_code',
        'temperature_2m_max',
        'temperature_2m_min',
        'apparent_temperature_max',
        'precipitation_probability_max',
        'precipitation_sum',
        'wind_speed_10m_max',
        'sunrise',
        'sunset',
    ];

    public function handle(array $cities): Collection
    {
        if ($cities === []) {
            return collect();
        }

        try {
            $response = Http::timeout(15)
                ->retry(4, 3000, throw: false)
                ->get(self::ENDPOINT, [
                    'latitude' => collect($cities)->pluck('latitude')->implode(','),
                    'longitude' => collect($cities)->pluck('longitude')->implode(','),
                    'daily' => implode(',', self::DAILY_METRICS),
                    'temperature_unit' => 'fahrenheit',
                    'wind_speed_unit' => 'mph',
                    'precipitation_unit' => 'inch',
                    'timezone' => 'auto',
                    'forecast_days' => 1,
                ]);
        } catch (ConnectionException $e) {
            Log::error('[open-meteo] Could not reach the forecast API.', [
                'message' => $e->getMessage(),
            ]);

            return collect();
        }

        if ($response->failed()) {
            Log::error('[open-meteo] Forecast request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return collect();
        }

        return $this->locations($response->json() ?? [])
            ->map(function (array $location, int $index) use ($cities): ?array {
                if (! isset($cities[$index], $location['daily']['weather_code'][0])) {
                    return null;
                }

                return $this->toForecast($cities[$index]['name'], $location['daily']);
            })
            ->filter()
            ->values();
    }

    private function locations(array $payload): Collection
    {
        return collect(array_is_list($payload) ? $payload : [$payload]);
    }

    private function toForecast(string $name, array $daily): array
    {
        return [
            'name' => $name,
            'condition' => WeatherCondition::fromWmoCode($daily['weather_code'][0]),
            'high' => (int) round((float) ($daily['temperature_2m_max'][0] ?? 0)),
            'low' => (int) round((float) ($daily['temperature_2m_min'][0] ?? 0)),
            'feels_like' => (int) round((float) ($daily['apparent_temperature_max'][0] ?? 0)),
            'chance_of_rain' => (int) round((float) ($daily['precipitation_probability_max'][0] ?? 0)),
            'precipitation' => round((float) ($daily['precipitation_sum'][0] ?? 0), 2),
            'wind_speed' => (int) round((float) ($daily['wind_speed_10m_max'][0] ?? 0)),
            'sunrise' => $this->toClockTime($daily['sunrise'][0] ?? null),
            'sunset' => $this->toClockTime($daily['sunset'][0] ?? null),
        ];
    }

    private function toClockTime(?string $timestamp): ?string
    {
        if (! $timestamp) {
            return null;
        }

        return CarbonImmutable::parse($timestamp)->format('g:i A');
    }
}
