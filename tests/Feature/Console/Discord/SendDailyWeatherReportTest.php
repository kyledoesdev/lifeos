<?php

use App\Actions\Api\Weather\GetDailyForecast;
use App\Enums\Discord\PingBeansRole;
use App\Enums\WeatherCondition;
use App\Models\WeatherReportCity;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const WEATHER_WEBHOOK = 'https://discord.com/api/webhooks/weather-report';

beforeEach(function () {
    config()->set('services.discord.royalty.weather-report.webhook_url', WEATHER_WEBHOOK);

    Sleep::fake();

    WeatherReportCity::factory()->philadelphia()->create();
});

it('sends a formatted weather embed to discord', function () {
    Http::fake([
        'api.open-meteo.com/*' => Http::response(openMeteoLocation()),
        WEATHER_WEBHOOK => Http::response('', 204),
    ]);

    $this->artisan('royal-tea:weather-report')->assertSuccessful();

    Http::assertSent(function (Request $request) {
        if ($request->url() !== WEATHER_WEBHOOK) {
            return false;
        }

        $embed = $request->data()['embeds'][0];
        $field = $embed['fields'][0];

        expect($embed['fields'])->toHaveCount(1)
            ->and($field['inline'])->toBeFalse()
            ->and($field['name'])->toContain('Philadelphia, PA')
            ->and($field['value'])->toContain('**93° / 76°**')
            ->toContain('Overcast')
            ->toContain('Feels like 102°')
            ->toContain('💧 35%')
            ->toContain('💨 11 mph')
            ->toContain('🌅 6:04 AM')
            ->toContain('🌇 8:07 PM')
            ->and($field['value'])->not->toContain('expected');

        return true;
    });
});

it('requests every configured city in a single open-meteo call', function () {
    Http::fake([
        'api.open-meteo.com/*' => Http::response(openMeteoLocation()),
        WEATHER_WEBHOOK => Http::response('', 204),
    ]);

    $this->artisan('royal-tea:weather-report')->assertSuccessful();

    Http::assertSentCount(2);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api.open-meteo.com')
        && $request['latitude'] === '39.9526'
        && $request['longitude'] === '-75.1652'
        && $request['temperature_unit'] === 'fahrenheit'
        && $request['forecast_days'] === 1);
});

it('handles a list response when several cities are requested', function () {
    Http::fake([
        'api.open-meteo.com/*' => Http::response([
            openMeteoLocation(),
            openMeteoLocation(weatherCode: 95, high: 80.2, low: 61.4),
        ]),
        WEATHER_WEBHOOK => Http::response('', 204),
    ]);

    $forecasts = (new GetDailyForecast)->handle([
        ['name' => 'Philadelphia, PA', 'latitude' => 39.9526, 'longitude' => -75.1652],
        ['name' => 'Denver, CO', 'latitude' => 39.7392, 'longitude' => -104.9903],
    ]);

    expect($forecasts)->toHaveCount(2)
        ->and($forecasts[1]['name'])->toBe('Denver, CO')
        ->and($forecasts[1]['condition'])->toBe(WeatherCondition::THUNDERSTORM)
        ->and($forecasts[1]['high'])->toBe(80)
        ->and($forecasts[1]['low'])->toBe(61);
});

it('shows expected precipitation when rain is forecast', function () {
    $location = openMeteoLocation(weatherCode: 65);
    $location['daily']['precipitation_sum'] = [0.42];

    Http::fake([
        'api.open-meteo.com/*' => Http::response($location),
        WEATHER_WEBHOOK => Http::response('', 204),
    ]);

    $this->artisan('royal-tea:weather-report')->assertSuccessful();

    Http::assertSent(function (Request $request) {
        if ($request->url() !== WEATHER_WEBHOOK) {
            return false;
        }

        expect($request->data()['embeds'][0]['fields'][0]['value'])
            ->toContain('🌂 0.42" expected')
            ->toContain('Heavy Rain');

        return true;
    });
});

it('stacks every city on its own row', function () {
    WeatherReportCity::factory()->create(['city' => 'Denver', 'state' => 'CO']);
    WeatherReportCity::factory()->create(['city' => 'Boulder', 'state' => 'CO']);

    Http::fake([
        'api.open-meteo.com/*' => Http::response([openMeteoLocation(), openMeteoLocation(), openMeteoLocation()]),
        WEATHER_WEBHOOK => Http::response('', 204),
    ]);

    $this->artisan('royal-tea:weather-report')->assertSuccessful();

    Http::assertSent(function (Request $request) {
        if ($request->url() !== WEATHER_WEBHOOK) {
            return false;
        }

        $fields = $request->data()['embeds'][0]['fields'];

        expect($fields)->toHaveCount(3)
            ->and(collect($fields)->pluck('inline')->all())->each->toBeFalse();

        // Every city but the last is followed by a blank line for breathing room.
        expect($fields[0]['value'])->toEndWith("\n\u{200B}")
            ->and($fields[1]['value'])->toEndWith("\n\u{200B}")
            ->and($fields[2]['value'])->not->toEndWith("\u{200B}");

        return true;
    });
});

it('mentions the beans role above the embed', function () {
    config()->set('services.discord.royalty.roles.beans', '1234567890123456789');
    PingBeansRole::WEATHER_REPORT->set(true);

    Http::fake([
        'api.open-meteo.com/*' => Http::response(openMeteoLocation()),
        WEATHER_WEBHOOK => Http::response('', 204),
    ]);

    $this->artisan('royal-tea:weather-report')->assertSuccessful();

    Http::assertSent(function (Request $request) {
        if ($request->url() !== WEATHER_WEBHOOK) {
            return false;
        }

        expect($request->data()['content'])->toBe('<@&1234567890123456789>')
            ->and($request->data()['allowed_mentions'])->toBe(['roles' => ['1234567890123456789']]);

        return true;
    });
});

it('sends without a mention when the beans role is not configured', function () {
    config()->set('services.discord.royalty.roles.beans', null);
    PingBeansRole::WEATHER_REPORT->set(true);

    Http::fake([
        'api.open-meteo.com/*' => Http::response(openMeteoLocation()),
        WEATHER_WEBHOOK => Http::response('', 204),
    ]);

    $this->artisan('royal-tea:weather-report')->assertSuccessful();

    Http::assertSent(function (Request $request) {
        if ($request->url() !== WEATHER_WEBHOOK) {
            return false;
        }

        expect($request->data())->not->toHaveKey('content')
            ->and($request->data())->not->toHaveKey('allowed_mentions');

        return true;
    });
});

it('sends without a mention while the beans toggle is off', function () {
    config()->set('services.discord.royalty.roles.beans', '1234567890123456789');

    Http::fake([
        'api.open-meteo.com/*' => Http::response(openMeteoLocation()),
        WEATHER_WEBHOOK => Http::response('', 204),
    ]);

    $this->artisan('royal-tea:weather-report')->assertSuccessful();

    Http::assertSent(function (Request $request) {
        if ($request->url() !== WEATHER_WEBHOOK) {
            return false;
        }

        expect($request->data())->not->toHaveKey('content')
            ->and($request->data())->not->toHaveKey('allowed_mentions')
            ->and($request->data())->toHaveKey('embeds');

        return true;
    });
});

it('labels each forecast with the concatenated city and state', function () {
    Http::fake([
        'api.open-meteo.com/*' => Http::response(openMeteoLocation()),
        WEATHER_WEBHOOK => Http::response('', 204),
    ]);

    $this->artisan('royal-tea:weather-report')->assertSuccessful();

    Http::assertSent(function (Request $request) {
        if ($request->url() !== WEATHER_WEBHOOK) {
            return false;
        }

        expect($request->data()['embeds'][0]['fields'][0]['name'])->toContain('Philadelphia, PA');

        return true;
    });
});

it('fails when no cities are configured', function () {
    WeatherReportCity::query()->delete();

    Http::fake();

    $this->artisan('royal-tea:weather-report')
        ->expectsOutputToContain('No weather report cities have been configured.')
        ->assertFailed();

    Http::assertNothingSent();
});

it('fails without posting to discord when the weather api errors', function () {
    Http::fake([
        'api.open-meteo.com/*' => Http::response('', 500),
        WEATHER_WEBHOOK => Http::response('', 204),
    ]);

    $this->artisan('royal-tea:weather-report')
        ->expectsOutputToContain('Failed to retrieve the daily weather report.')
        ->assertFailed();

    Http::assertNotSent(fn (Request $request) => $request->url() === WEATHER_WEBHOOK);
});

it('fails when discord rejects the webhook', function () {
    Http::fake([
        'api.open-meteo.com/*' => Http::response(openMeteoLocation()),
        WEATHER_WEBHOOK => Http::response('', 404),
    ]);

    $this->artisan('royal-tea:weather-report')
        ->expectsOutputToContain('Failed to send the daily weather report to Discord.')
        ->assertFailed();
});

it('retries a rate limited discord webhook before giving up', function () {
    Http::fake([
        'api.open-meteo.com/*' => Http::response(openMeteoLocation()),
        WEATHER_WEBHOOK => Http::sequence()
            ->push('', 429)
            ->push('', 429)
            ->push('', 204),
    ]);

    $this->artisan('royal-tea:weather-report')->assertSuccessful();

    Http::assertSentCount(4);
});

it('retries a throttled weather api before giving up', function () {
    Http::fake([
        'api.open-meteo.com/*' => Http::sequence()
            ->push('', 429)
            ->push(openMeteoLocation(), 200),
        WEATHER_WEBHOOK => Http::response('', 204),
    ]);

    $this->artisan('royal-tea:weather-report')->assertSuccessful();

    Http::assertSentCount(3);
});

it('fails gracefully when the weather api cannot be reached', function () {
    Http::fake([
        'api.open-meteo.com/*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out'),
        WEATHER_WEBHOOK => Http::response('', 204),
    ]);

    $this->artisan('royal-tea:weather-report')
        ->expectsOutputToContain('Failed to retrieve the daily weather report.')
        ->assertFailed();

    Http::assertNotSent(fn (Request $request) => $request->url() === WEATHER_WEBHOOK);
});

it('fails gracefully when discord cannot be reached', function () {
    Http::fake([
        'api.open-meteo.com/*' => Http::response(openMeteoLocation()),
        WEATHER_WEBHOOK => fn () => throw new ConnectionException('cURL error 6: Could not resolve host'),
    ]);

    $this->artisan('royal-tea:weather-report')
        ->expectsOutputToContain('Could not reach Discord to send the daily weather report.')
        ->assertFailed();
});

/**
 * Open-Meteo returns a bare object for one coordinate pair and a list for many.
 *
 * @return array<string, mixed>
 */
function openMeteoLocation(int $weatherCode = 3, float $high = 93.4, float $low = 75.9): array
{
    return [
        'latitude' => 39.916214,
        'longitude' => -75.06144,
        'timezone' => 'America/New_York',
        'daily' => [
            'time' => ['2026-08-07'],
            'weather_code' => [$weatherCode],
            'temperature_2m_max' => [$high],
            'temperature_2m_min' => [$low],
            'apparent_temperature_max' => [102.2],
            'precipitation_probability_max' => [35],
            'precipitation_sum' => [0.0],
            'wind_speed_10m_max' => [11.4],
            'sunrise' => ['2026-08-07T06:04'],
            'sunset' => ['2026-08-07T20:07'],
        ],
    ];
}
