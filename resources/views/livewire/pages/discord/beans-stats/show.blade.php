@php
    /*
     * Categorical slots in a fixed order, each stepped separately for light and dark.
     * A person keeps the same color in every chart on the page.
     */
    $palette = [
        ['text' => 'text-[#2a78d6] dark:text-[#3987e5]', 'bg' => 'bg-[#2a78d6] dark:bg-[#3987e5]'],
        ['text' => 'text-[#eb6834] dark:text-[#d95926]', 'bg' => 'bg-[#eb6834] dark:bg-[#d95926]'],
        ['text' => 'text-[#1baf7a] dark:text-[#199e70]', 'bg' => 'bg-[#1baf7a] dark:bg-[#199e70]'],
        ['text' => 'text-[#eda100] dark:text-[#c98500]', 'bg' => 'bg-[#eda100] dark:bg-[#c98500]'],
        ['text' => 'text-[#e87ba4] dark:text-[#d55181]', 'bg' => 'bg-[#e87ba4] dark:bg-[#d55181]'],
        ['text' => 'text-[#008300] dark:text-[#0ca30c]', 'bg' => 'bg-[#008300] dark:bg-[#0ca30c]'],
    ];
    $everyoneElse = ['text' => 'text-zinc-400 dark:text-zinc-500', 'bg' => 'bg-zinc-400 dark:bg-zinc-500'];
    $color = fn (?int $slot): array => $slot === null ? $everyoneElse : $palette[$slot];

    $stats = $this->stats;
    $dateFormat = $period === 'week'
        ? ['month' => 'short', 'day' => 'numeric']
        : ['month' => 'short', 'year' => '2-digit'];
    $tooltipFormat = $period === 'week'
        ? ['month' => 'short', 'day' => 'numeric', 'year' => 'numeric']
        : ['month' => 'long', 'year' => 'numeric'];
@endphp

<div>
    <x-slot name="header">Royal Tea Stats</x-slot>

    @unless ($this->hasBotToken)
        <flux:callout variant="warning" icon="exclamation-triangle" class="mb-6">
            <flux:callout.heading>Add a Discord bot token</flux:callout.heading>
            <flux:callout.text>
                Make a bot at discord.com/developers/applications, turn on its Message Content intent, invite it
                to the server with View Channels and Read Message History, then put its token in
                <code>DISCORD_ROYALTY_BEANS_BOT_TOKEN</code> in <code>.env</code>.
            </flux:callout.text>
        </flux:callout>
    @endunless

    @if ($this->channel)
        {{-- Channel --}}
        <flux:card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ $this->channel->label }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ $this->channel->synced_at ? 'Last updated: '.$this->channel->synced_at->diffForHumans() : 'Not synced yet' }}
                    </flux:text>
                </div>

                @auth
                    <flux:button wire:click="sync" size="sm" icon="arrow-path" :disabled="! $this->hasBotToken">
                        Sync now
                    </flux:button>
                @endauth
            </div>

            @if ($stats['totals']['missing_durations'] > 0)
                <flux:callout variant="warning" icon="exclamation-triangle" class="mt-4">
                    <flux:callout.text>
                        {{ number_format($stats['totals']['missing_durations']) }} voice messages came back without a length.
                        Discord leaves attachments out unless the bot has the Message Content intent. Turn it on, then run
                        <code>php artisan discord:sync-voice-messages --fresh</code>.
                    </flux:callout.text>
                </flux:callout>
            @endif
        </flux:card>
    @endif

    @if ($stats && $stats['totals']['voice_messages'] > 0)
        @php
            $people = collect($stats['people']);
            $top = $people->first();
        @endphp

        {{-- Headline numbers --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
            <flux:card>
                <flux:text>Voice messages</flux:text>
                <flux:heading size="xl" class="mt-1">{{ number_format($stats['totals']['voice_messages']) }}</flux:heading>
                <flux:text class="mt-1">from {{ $stats['totals']['people'] }} {{ str('person')->plural($stats['totals']['people']) }}</flux:text>
            </flux:card>

            <flux:card>
                <flux:text>Total length</flux:text>
                <flux:heading size="xl" class="mt-1">{{ $this->duration($stats['totals']['seconds']) }}</flux:heading>
                <flux:text class="mt-1">{{ $this->duration($stats['totals']['seconds'] / $stats['totals']['voice_messages']) }} on average</flux:text>
            </flux:card>

            <flux:card>
                <flux:text>Longest one</flux:text>
                <flux:heading size="xl" class="mt-1">{{ $this->duration($stats['longest']['seconds']) }}</flux:heading>
                <flux:text class="mt-1">{{ $stats['longest']['name'] }} on {{ $stats['longest']['sent_at']->format('M j, Y') }}</flux:text>
            </flux:card>

            <flux:card>
                <flux:text>Sends the most</flux:text>
                <flux:heading size="xl" class="mt-1 truncate">{{ $top['name'] }}</flux:heading>
                <flux:text class="mt-1">
                    {{ number_format($top['voice_messages']) }}, or {{ round($top['voice_messages'] / $stats['totals']['voice_messages'] * 100) }}% of them
                </flux:text>
            </flux:card>
        </div>

        {{-- Per person --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-6">
            @include('livewire.pages.discord.beans-stats.partials.bar-list', [
                'title' => 'Most voice messages',
                'people' => $people,
                'field' => 'voice_messages',
                'label' => fn (array $person): string => number_format($person['voice_messages']),
            ])

            @include('livewire.pages.discord.beans-stats.partials.bar-list', [
                'title' => 'Most time talking',
                'people' => $people->sortByDesc('seconds'),
                'field' => 'seconds',
                'label' => fn (array $person): string => $this->duration($person['seconds']),
            ])

            @include('livewire.pages.discord.beans-stats.partials.bar-list', [
                'title' => 'Longest on average',
                'people' => $people->sortByDesc('average_seconds'),
                'field' => 'average_seconds',
                'label' => fn (array $person): string => $this->duration($person['average_seconds']),
            ])
        </div>

        {{-- Over time --}}
        <flux:card class="mt-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <flux:heading size="lg">Voice messages over time</flux:heading>

                <flux:radio.group wire:model.live="period" variant="segmented" size="sm">
                    <flux:radio value="month" label="By month" />
                    <flux:radio value="week" label="By week" />
                </flux:radio.group>
            </div>

            <flux:chart
                :value="$stats['timeline']"
                class="aspect-2/1 sm:aspect-3/1"
                wire:key="voice-timeline-{{ $period }}"
            >
                <flux:chart.svg>
                    <flux:chart.stack>
                        @foreach ($stats['series'] as $series)
                            <flux:chart.bar :field="$series['key']" class="{{ $color($series['slot'])['text'] }}" />
                        @endforeach
                    </flux:chart.stack>

                    <flux:chart.axis axis="x" field="date" :format="$dateFormat">
                        <flux:chart.axis.line />
                        <flux:chart.axis.tick />
                    </flux:chart.axis>

                    <flux:chart.axis axis="y">
                        <flux:chart.axis.grid />
                        <flux:chart.axis.tick />
                    </flux:chart.axis>
                </flux:chart.svg>

                <flux:chart.tooltip>
                    <flux:chart.tooltip.heading field="date" :format="$tooltipFormat" />

                    @foreach ($stats['series'] as $series)
                        <flux:chart.tooltip.value :field="$series['key']" :label="$series['name']">
                            <flux:chart.tooltip.indicator class="{{ $color($series['slot'])['bg'] }}" />
                        </flux:chart.tooltip.value>
                    @endforeach
                </flux:chart.tooltip>
            </flux:chart>

            <div class="flex flex-wrap gap-x-4 mt-2">
                @foreach ($stats['series'] as $series)
                    <flux:chart.legend :label="$series['name']">
                        <flux:chart.legend.indicator class="{{ $color($series['slot'])['bg'] }}" />
                    </flux:chart.legend>
                @endforeach
            </div>
        </flux:card>

        {{-- Everyone, in full --}}
        <flux:card class="mt-6">
            <flux:heading size="lg" class="mb-2">Everyone</flux:heading>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Person</flux:table.column>
                    <flux:table.column align="end">Voice messages</flux:table.column>
                    <flux:table.column align="end">Total</flux:table.column>
                    <flux:table.column align="end">Average</flux:table.column>
                    <flux:table.column align="end">Longest</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($people as $person)
                        <flux:table.row :key="'person-'.$person['author_id']">
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <span class="size-2.5 shrink-0 rounded-full {{ $color($person['slot'])['bg'] }}"></span>
                                    {{ $person['name'] }}
                                </div>
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($person['voice_messages']) }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $this->duration($person['seconds']) }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $this->duration($person['average_seconds']) }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $this->duration($person['longest_seconds']) }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @elseif ($stats)
        <flux:card class="mt-6">
            <div class="flex justify-center my-4">
                <flux:badge>No voice messages saved yet.</flux:badge>
            </div>
        </flux:card>
    @endif
</div>
