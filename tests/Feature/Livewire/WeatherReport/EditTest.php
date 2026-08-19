<?php

use App\Enums\Discord\PingBeansRole;
use App\Livewire\Pages\WeatherReport\Edit;
use App\Livewire\Pages\WeatherReport\Tables\Row;
use App\Models\User;
use App\Models\WeatherReportCity;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('services.geocodio.api_key', 'test-key');

    $this->actingAs(User::factory()->create());
});

it('gates the weather report edit page behind auth', function () {
    auth()->logout();

    $this->get(route('weather_report.edit'))->assertRedirect(route('login'));
});

it('loads the page and links from the dashboard', function () {
    WeatherReportCity::factory()->philadelphia()->create();

    $this->get(route('weather_report.edit'))
        ->assertOk()
        ->assertSee('Philadelphia');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('weather_report.edit'));
});

it('only searches geocodio when the search button is pressed', function () {
    Http::fake(['api.geocod.io/*' => Http::response(['results' => [geocodioResult()]])]);

    $component = Livewire::test(Edit::class)->set('phrase', 'Philadelphia');

    Http::assertNothingSent();

    $component->call('searchCities')->assertCount('searchedCities', 1);

    Http::assertSentCount(1);
});

it('selects a city and adds it', function () {
    Http::fake(['api.geocod.io/*' => Http::response(['results' => [geocodioResult()]])]);

    Livewire::test(Edit::class)
        ->set('phrase', 'Philadelphia')
        ->call('searchCities')
        ->call('selectCity', 0)
        ->assertSet('form.city', 'Philadelphia')
        ->assertSet('form.state', 'PA')
        ->assertSet('form.latitude', 39.9526)
        ->assertSet('form.longitude', -75.1652)
        ->assertSet('phrase', '')
        ->assertSet('searchedCities', [])
        ->call('store')
        ->assertHasNoErrors()
        ->assertSet('selectedCity', null);

    $city = WeatherReportCity::query()->sole();

    expect($city->name)->toBe('Philadelphia, PA');
});

it('ignores a selection that was not in the returned list', function () {
    Http::fake(['api.geocod.io/*' => Http::response(['results' => [geocodioResult()]])]);

    Livewire::test(Edit::class)
        ->set('phrase', 'Philadelphia')
        ->call('searchCities')
        ->call('selectCity', 99)
        ->assertSet('selectedCity', null);

    expect(WeatherReportCity::query()->count())->toBe(0);
});

it('can clear a selection and search again', function () {
    Http::fake(['api.geocod.io/*' => Http::response(['results' => [geocodioResult()]])]);

    Livewire::test(Edit::class)
        ->set('phrase', 'Philadelphia')
        ->call('searchCities')
        ->call('selectCity', 0)
        ->call('clearSelection')
        ->assertSet('selectedCity', null)
        ->assertSet('form.city', '');
});

it('does not hit geocodio for phrases under three characters', function () {
    Http::fake();

    Livewire::test(Edit::class)
        ->set('phrase', 'Ph')
        ->call('searchCities')
        ->assertSet('searchedCities', []);

    Http::assertNothingSent();
});

it('discards geocodio results that are not a city', function () {
    Http::fake([
        'api.geocod.io/*' => Http::response(['results' => [
            ['address_components' => ['state' => 'PA'], 'location' => ['lat' => 39.9, 'lng' => -75.1]],
            geocodioResult(),
        ]]),
    ]);

    Livewire::test(Edit::class)
        ->set('phrase', 'Philadelphia')
        ->call('searchCities')
        ->assertCount('searchedCities', 1);
});

it('will not add the same city and state twice', function () {
    WeatherReportCity::factory()->philadelphia()->create();

    Http::fake(['api.geocod.io/*' => Http::response(['results' => [geocodioResult()]])]);

    Livewire::test(Edit::class)
        ->set('phrase', 'Philadelphia')
        ->call('searchCities')
        ->call('selectCity', 0)
        ->call('store')
        ->assertHasErrors('form.city');

    expect(WeatherReportCity::query()->count())->toBe(1);
});

it('allows the same city name in a different state', function () {
    WeatherReportCity::factory()->create(['city' => 'Portland', 'state' => 'OR']);

    Http::fake(['api.geocod.io/*' => Http::response([
        'results' => [geocodioResult('Portland', 'ME', 43.6591, -70.2568)],
    ])]);

    Livewire::test(Edit::class)
        ->set('phrase', 'Portland')
        ->call('searchCities')
        ->call('selectCity', 0)
        ->call('store')
        ->assertHasNoErrors();

    expect(WeatherReportCity::query()->count())->toBe(2);
});

it('filters the table by city or state', function () {
    WeatherReportCity::factory()->philadelphia()->create();
    WeatherReportCity::factory()->create(['city' => 'Denver', 'state' => 'CO']);

    $component = Livewire::test(Edit::class);

    expect($component->set('search', 'Denver')->instance()->cities()->pluck('city')->all())->toBe(['Denver'])
        ->and($component->set('search', 'PA')->instance()->cities()->pluck('city')->all())->toBe(['Philadelphia']);
});

it('defaults to the model ordering of state then city', function () {
    WeatherReportCity::factory()->philadelphia()->create();
    WeatherReportCity::factory()->create(['city' => 'Denver', 'state' => 'CO']);
    WeatherReportCity::factory()->create(['city' => 'Boulder', 'state' => 'CO']);

    $cities = Livewire::test(Edit::class)->instance()->cities();

    expect($cities->pluck('name')->all())
        ->toBe(['Boulder, CO', 'Denver, CO', 'Philadelphia, PA']);
});

it('sorts the table by a column', function () {
    Livewire::test(Edit::class)
        ->assertSet('sortBy', '')
        ->assertSet('sortDirection', 'asc')
        ->call('sort', 'latitude')
        ->assertSet('sortBy', 'latitude')
        ->assertSet('sortDirection', 'asc')
        ->call('sort', 'latitude')
        ->assertSet('sortDirection', 'desc');
});

it('deletes a city from its row', function () {
    $city = WeatherReportCity::factory()->philadelphia()->create();

    Livewire::test(Row::class, ['city' => $city])
        ->call('destroy')
        ->assertDispatched('weather-report-city-deleted');

    expect(WeatherReportCity::query()->count())->toBe(0);
});

it('persists the beans mention toggle to the cache store', function () {
    Livewire::test(Edit::class)
        ->assertSet('mentionsBeans', false)
        ->set('mentionsBeans', true);

    expect(PingBeansRole::WEATHER_REPORT->enabled())->toBeTrue();

    Livewire::test(Edit::class)
        ->assertSet('mentionsBeans', true)
        ->set('mentionsBeans', false);

    expect(PingBeansRole::WEATHER_REPORT->enabled())->toBeFalse();
});

it('shows the beans mention toggle on the page', function () {
    $this->get(route('weather_report.edit'))
        ->assertOk()
        ->assertSee('Weather Report Settings')
        ->assertSee('Ping the Beans role');
});

function geocodioResult(string $city = 'Philadelphia', string $state = 'PA', float $lat = 39.9526, float $lng = -75.1652): array
{
    return [
        'address_components' => ['city' => $city, 'state' => $state, 'country' => 'US'],
        'formatted_address' => "{$city}, {$state}",
        'location' => ['lat' => $lat, 'lng' => $lng],
        'accuracy' => 1,
        'accuracy_type' => 'place',
    ];
}
