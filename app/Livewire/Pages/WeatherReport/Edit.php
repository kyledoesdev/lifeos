<?php

namespace App\Livewire\Pages\WeatherReport;

use App\Actions\Api\Geocodio\SearchCities;
use App\Enums\Discord\PingBeansRole;
use App\Livewire\Concerns\HasTableHelpers;
use App\Livewire\Forms\WeatherReportCityForm;
use App\Models\WeatherReportCity;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Edit extends Component
{
    use HasTableHelpers;
    use WithPagination;

    public WeatherReportCityForm $form;

    public array $searchedCities = [];

    public ?array $selectedCity = null;

    public string $phrase = '';

    public bool $mentionsBeans = false;

    /**
     * An empty sort defers to the model's default state then city ordering.
     */
    public function mount(): void
    {
        $this->sortBy = '';
        $this->sortDirection = 'asc';

        $this->mentionsBeans = PingBeansRole::WEATHER_REPORT->enabled();
    }

    public function updatedMentionsBeans(bool $enabled): void
    {
        PingBeansRole::WEATHER_REPORT->set($enabled);

        Flux::toast(
            variant: 'success',
            text: $enabled
                ? 'The Beans role will be pinged with the daily weather report.'
                : 'The Beans role will no longer be pinged.',
            duration: 3000,
        );
    }

    public function render()
    {
        return view('livewire.pages.weather-report.edit');
    }

    #[Computed]
    public function cities()
    {
        return WeatherReportCity::query()
            ->when($this->search != '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->where('city', 'LIKE', "%$this->search%")
                    ->orWhere('state', 'LIKE', "%$this->search%")
            ))
            ->tap(fn ($query) => $this->sortBy ? $query->orderBy($this->sortBy, $this->sortDirection) : $query)
            ->paginate($this->perPage);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function searchCities(): void
    {
        $this->searchedCities = (new SearchCities)->handle($this->phrase)->all();

        if ($this->searchedCities === []) {
            Flux::toast(variant: 'danger', text: "No cities found for search term: {$this->phrase}.", duration: 3000);
        }
    }

    public function selectCity(int $index): void
    {
        $city = $this->searchedCities[$index] ?? null;

        if (! $city) {
            Flux::toast(variant: 'danger', text: 'You can not add a city that was not in the returned list.', duration: 3000);

            return;
        }

        $this->selectedCity = $city;
        $this->form->fillFromSearch($city);

        $this->reset('phrase', 'searchedCities');
    }

    public function clearSelection(): void
    {
        $this->reset('selectedCity');

        $this->form->reset();
    }

    public function store(): void
    {
        $this->form->store();

        $this->reset('phrase', 'selectedCity', 'searchedCities');

        unset($this->cities);
    }

    #[On('weather-report-city-deleted')]
    public function refreshCities(): void
    {
        unset($this->cities);
    }
}
