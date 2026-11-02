<div class="space-y-6">
    @foreach ($platforms as $platform)
        <flux:card wire:key="platform-{{ $platform->value }}" class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <flux:icon :name="$platform->icon()" class="w-6 h-6" />

                    <div>
                        <flux:heading level="3" size="lg">{{ $platform->label() }}</flux:heading>

                        @if ($platform->isConnectedFor(auth()->user()))
                            <flux:badge color="green" size="sm">Connected</flux:badge>
                        @else
                            <flux:link :href="route('connections.edit')" class="text-sm">Not connected. Connect it on the connections page.</flux:link>
                        @endif
                    </div>
                </div>

                <flux:switch wire:model.live="enabled.{{ $platform->value }}" align="left" label="Post when I go live" />
            </div>

            <div class="space-y-2">
                <flux:textarea
                    wire:model="templateForm.templates.{{ $platform->value }}"
                    label="Go live post"
                    rows="6"
                    resize="vertical"
                />

                <flux:text size="sm">
                    Use <code>{title}</code>, <code>{category}</code>, and <code>{url}</code> to fill in the stream's details.
                    Long titles are shortened to fit {{ $platform->characterLimit() }} characters.
                </flux:text>
            </div>

            <div class="flex">
                <flux:button variant="ghost" size="sm" wire:click="resetTemplate('{{ $platform->value }}')">Reset to default</flux:button>

                <flux:spacer />

                <flux:button variant="primary" size="sm" wire:click="saveTemplate('{{ $platform->value }}')">Save template</flux:button>
            </div>
        </flux:card>
    @endforeach
</div>
