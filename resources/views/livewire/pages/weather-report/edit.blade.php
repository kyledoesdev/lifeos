<div>
    <x-slot name="header">Weather Report</x-slot>

    <flux:card>
        {{-- Search & Create --}}
        <div class="flex justify-end items-center gap-2">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Search cities..."
                size="sm"
                class="max-w-xs"
                clearable
            />

            <flux:modal.trigger name="create-weather-report-city">
                <flux:button variant="primary" size="sm" icon="plus">Add City</flux:button>
            </flux:modal.trigger>
        </div>

        {{-- Table of Cities --}}
        <div class="py-6">
            <div class="max-w-7xl mx-auto">
                <div class="overflow-hidden shadow-2xs sm:rounded-lg p-6">
                    <flux:table :paginate="$this->cities">
                        @forelse ($this->cities as $city)
                            @if ($loop->first)
                                <flux:table.columns>
                                    <flux:table.column sortable :sorted="$sortBy === 'city'" :direction="$sortDirection" wire:click="sort('city')">City</flux:table.column>
                                    <flux:table.column sortable :sorted="$sortBy === 'state'" :direction="$sortDirection" wire:click="sort('state')">State</flux:table.column>
                                    <flux:table.column sortable :sorted="$sortBy === 'latitude'" :direction="$sortDirection" wire:click="sort('latitude')">Latitude</flux:table.column>
                                    <flux:table.column sortable :sorted="$sortBy === 'longitude'" :direction="$sortDirection" wire:click="sort('longitude')">Longitude</flux:table.column>
                                    <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Created</flux:table.column>
                                    <flux:table.column sortable :sorted="$sortBy === 'updated_at'" :direction="$sortDirection" wire:click="sort('updated_at')">Updated</flux:table.column>
                                    <flux:table.column>Actions</flux:table.column>
                                </flux:table.columns>
                            @endif

                            <livewire:pages.weather-report.tables.row
                                :city="$city"
                                :wire:key="$city->getKey()"
                            />
                        @empty
                            <flux:card>
                                <div class="flex justify-center my-4">
                                    <flux:badge>No cities found.</flux:badge>
                                </div>
                            </flux:card>
                        @endforelse
                    </flux:table>
                </div>
            </div>
        </div>
    </flux:card>

    {{-- Settings --}}
    <flux:card class="mt-6">
        <flux:heading level="3" size="lg" class="mb-4">Weather Report Settings</flux:heading>

        <flux:switch wire:model.live="mentionsBeans" align="left" label="Ping the Beans role" />
    </flux:card>

    {{-- Create Modal --}}
    <flux:modal name="create-weather-report-city" class="space-y-6 md:w-96">
        @if (is_null($selectedCity))
            <div>
                <flux:heading size="lg">Add a City</flux:heading>
                <flux:text class="mt-2">Search for a city to add it to the daily Discord weather report.</flux:text>
            </div>

            <flux:input.group>
                <flux:input
                    wire:model="phrase"
                    wire:keydown.enter.prevent="searchCities"
                    placeholder="Search for a city..."
                />

                <flux:button type="submit" icon="magnifying-glass" wire:click="searchCities" />
            </flux:input.group>

            <div class="flex flex-col gap-2">
                @foreach ($searchedCities as $index => $searchedCity)
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="plus"
                        align="start"
                        wire:key="searched-city-{{ $index }}"
                        wire:click="selectCity({{ $index }})"
                    >
                        {{ $searchedCity['city'] }}, {{ $searchedCity['state'] }}
                    </flux:button>
                @endforeach
            </div>
        @else
            <div>
                <flux:heading size="lg">Add {{ $form->name() }} to the weather report.</flux:heading>
            </div>

            <flux:callout icon="map-pin">
                <flux:callout.heading>{{ $form->name() }}</flux:callout.heading>
                <flux:callout.text>{{ $form->latitude }}, {{ $form->longitude }}</flux:callout.text>
            </flux:callout>

            <flux:error name="form.city" />

            <div class="flex my-2">
                <flux:button variant="ghost" size="sm" wire:click="clearSelection">Search again</flux:button>

                <flux:spacer />

                <flux:button type="submit" variant="primary" wire:click="store">Add</flux:button>
            </div>
        @endif
    </flux:modal>
</div>
