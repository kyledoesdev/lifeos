<div>
    <x-slot name="header">Manage External Connections</x-slot>

    @php
        $connections = [
            'spotify' => [
                'connection' => auth()->user()->connections->firstWhere('type', App\Enums\ConnectionType::SPOTIFY->value),
                'icon' => 'musical-note',
                'color' => 'lime',
                'name' => 'Spotify'
            ],
            'twitch' => [
                'connection' => auth()->user()->connections->firstWhere('type', App\Enums\ConnectionType::TWITCH->value),
                'icon' => 'tv',
                'color' => 'purple',
                'name' => 'Twitch'
            ],
            'bluesky' => [
                'connection' => auth()->user()->connections->firstWhere('type', App\Enums\ConnectionType::BLUESKY->value),
                'icon' => 'at-symbol',
                'color' => 'sky',
                'name' => 'Bluesky',
                'action' => 'connectBluesky',
            ]
        ];
    @endphp

    <flux:card>
        <div class="space-y-6">
            @foreach ($connections as $type => $config)
                <div class="flex items-center justify-between p-4 border rounded-lg">
                    <div class="flex items-center space-x-3">
                        <flux:icon :name="$config['icon']" class="w-6 h-6" />
                        <div>
                            <h3 class="font-medium">{{ $config['name'] }}</h3>
                            @if ($config['connection'])
                                <flux:badge :color="$config['color']" size="sm">
                                    Connected • Updated {{ $config['connection']->updated_at }}
                                </flux:badge>
                            @else
                                <p class="text-sm text-gray-500">Not connected</p>
                            @endif
                        </div>
                    </div>

                    @isset($config['action'])
                        <flux:button
                            variant="outline"
                            :size="$config['connection'] ? 'sm' : 'base'"
                            :icon="$config['icon']"
                            wire:click="{{ $config['action'] }}"
                        >
                            {{ $config['connection'] ? 'Reconnect' : 'Connect' }} {{ $config['name'] }}
                        </flux:button>
                    @else
                        <flux:button
                            variant="outline"
                            :size="$config['connection'] ? 'sm' : 'base'"
                            :icon="$config['icon']"
                            :href="route('connect', ['type' => $type])"
                        >
                            {{ $config['connection'] ? 'Reconnect' : 'Connect' }} {{ $config['name'] }}
                        </flux:button>
                    @endisset
                </div>
            @endforeach
        </div>
    </flux:card>
</div>
