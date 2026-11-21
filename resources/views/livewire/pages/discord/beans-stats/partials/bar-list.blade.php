@php
    $max = max($people->max($field), 1);
@endphp

<flux:card>
    <flux:heading size="lg" class="mb-4">{{ $title }}</flux:heading>

    <ul class="space-y-3">
        @foreach ($people as $person)
            <li wire:key="{{ $field }}-{{ $person['author_id'] }}">
                <div class="flex items-baseline justify-between gap-2 text-sm">
                    <span class="truncate text-zinc-800 dark:text-zinc-100">{{ $person['name'] }}</span>
                    <span class="shrink-0 tabular-nums text-zinc-500 dark:text-zinc-400">{{ $label($person) }}</span>
                </div>

                <div class="mt-1 h-2 rounded-full bg-zinc-100 dark:bg-zinc-700">
                    <div
                        class="h-2 rounded-full {{ $color($person['slot'])['bg'] }}"
                        style="width: {{ max($person[$field] / $max * 100, 1) }}%"
                    ></div>
                </div>
            </li>
        @endforeach
    </ul>
</flux:card>
