<flux:card body="divided">
    <flux:card.header>
        <flux:card.heading level="3" size="lg">Stream History</flux:card.heading>

        <flux:card.actions>
            <x-per-page-selector />

            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Search streams..."
                size="sm"
                class="max-w-xs"
                clearable
            />
        </flux:card.actions>
    </flux:card.header>

    <flux:card.body>
        <flux:table :paginate="$this->streams">
            @forelse ($this->streams as $stream)
                @if ($loop->first)
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$sortBy === 'started_at'" :direction="$sortDirection" wire:click="sort('started_at')">Started</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'title'" :direction="$sortDirection" wire:click="sort('title')">Title</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'category_name'" :direction="$sortDirection" wire:click="sort('category_name')">Category</flux:table.column>
                        <flux:table.column>Duration</flux:table.column>
                        <flux:table.column>Discord</flux:table.column>
                        @foreach ($platforms as $platform)
                            <flux:table.column>{{ $platform->label() }}</flux:table.column>
                        @endforeach
                    </flux:table.columns>
                @endif

                <flux:table.row :key="'stream-'.$stream->id">
                    <flux:table.cell class="whitespace-nowrap">{{ $stream->started_at->inUserTimezone()->format('M j, Y g:i A') }}</flux:table.cell>

                    <flux:table.cell>{{ $stream->title_label }}</flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">{{ $stream->category_label }}</flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">
                        @if ($stream->isLive())
                            <flux:badge color="red" size="sm" icon="signal">Live</flux:badge>
                        @else
                            {{ $stream->duration ?? '—' }}
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($stream->discord_notified_at)
                            <flux:icon.check-circle variant="mini" class="text-green-500" />
                        @else
                            <flux:icon.x-circle variant="mini" class="text-red-500" />
                        @endif
                    </flux:table.cell>

                    @foreach ($platforms as $platform)
                        @php $post = $stream->socialMediaPosts->firstWhere('platform', $platform); @endphp

                        <flux:table.cell class="whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                @if ($post?->wasSent())
                                    <a href="{{ $post->url }}" target="_blank" rel="noopener">
                                        <flux:badge :color="$post->status->color()" size="sm" icon:trailing="arrow-top-right-on-square">
                                            {{ $post->status->label() }}
                                        </flux:badge>
                                    </a>
                                @elseif ($post)
                                    <flux:tooltip :content="$post->error">
                                        <flux:badge :color="$post->status->color()" size="sm">{{ $post->status->label() }}</flux:badge>
                                    </flux:tooltip>
                                @elseif (! $stream->isLive())
                                    <span class="text-zinc-400">—</span>
                                @endif

                                @if ($stream->isLive() && ! $post?->wasSent())
                                    <flux:button
                                        size="sm"
                                        icon="paper-airplane"
                                        inset="top bottom"
                                        wire:click="postNow({{ $stream->id }}, '{{ $platform->value }}')"
                                        wire:confirm="Post this stream to {{ $platform->label() }} now?"
                                    >
                                        {{ $post ? 'Retry' : 'Post now' }}
                                    </flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    @endforeach
                </flux:table.row>
            @empty
                <flux:card>
                    <div class="flex justify-center my-4">
                        <flux:badge>No streams found.</flux:badge>
                    </div>
                </flux:card>
            @endforelse
        </flux:table>
    </flux:card.body>
</flux:card>
