<div>
    <x-slot name="header">Live Streams</x-slot>

    <flux:tab.group>
        <flux:tabs>
            <flux:tab name="history" icon="clock">History</flux:tab>
            <flux:tab name="platforms" icon="megaphone">Platforms</flux:tab>
            <flux:tab name="rules" icon="adjustments-horizontal">Rules</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="history">
            @include('livewire.pages.live-streams.partials.history')
        </flux:tab.panel>

        <flux:tab.panel name="platforms">
            @include('livewire.pages.live-streams.partials.platforms')
        </flux:tab.panel>

        <flux:tab.panel name="rules">
            @include('livewire.pages.live-streams.partials.rules')
        </flux:tab.panel>
    </flux:tab.group>
</div>
