<flux:card>
    <div class="flex justify-between items-center gap-2">
        <div>
            <flux:heading level="3" size="lg">Category Rules</flux:heading>
            <flux:text class="mt-2">Going live in a category without a rule never posts.</flux:text>
        </div>

        <flux:modal.trigger name="create-stream-category-rule">
            <flux:button variant="primary" size="sm" icon="plus">Add Rule</flux:button>
        </flux:modal.trigger>
    </div>

    <div class="py-6">
        <flux:table>
            @forelse ($this->categoryRules as $rule)
                @if ($loop->first)
                    <flux:table.columns>
                        <flux:table.column>Category</flux:table.column>
                        @foreach ($platforms as $platform)
                            <flux:table.column>{{ $platform->label() }}</flux:table.column>
                        @endforeach
                        <flux:table.column>Actions</flux:table.column>
                    </flux:table.columns>
                @endif

                <flux:table.row :key="'rule-'.$rule->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            @if ($rule->box_art_url)
                                <img src="{{ $rule->box_art_url }}" alt="{{ $rule->category_name }}" width="36" height="48" class="rounded" />
                            @endif

                            <span class="whitespace-nowrap">{{ $rule->category_name }}</span>
                        </div>
                    </flux:table.cell>

                    @foreach ($platforms as $platform)
                        <flux:table.cell>
                            <flux:checkbox
                                :checked="$rule->allows($platform)"
                                wire:click="togglePlatform({{ $rule->id }}, '{{ $platform->value }}')"
                            />
                        </flux:table.cell>
                    @endforeach

                    <flux:table.cell>
                        <flux:button
                            variant="danger"
                            size="sm"
                            icon="trash"
                            inset="top bottom"
                            wire:click="destroyRule({{ $rule->id }})"
                            wire:confirm="Are you sure you want to remove the rule for {{ $rule->category_name }}?"
                        />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:card>
                    <div class="flex justify-center my-4">
                        <flux:badge>No category rules yet.</flux:badge>
                    </div>
                </flux:card>
            @endforelse
        </flux:table>
    </div>

    {{-- Create Modal --}}
    <flux:modal name="create-stream-category-rule" class="space-y-6 md:w-1/2">
        @if ($ruleForm->category_id === '')
            <div>
                <flux:heading size="lg">Add a Category Rule</flux:heading>
                <flux:text class="mt-2">Search Twitch for the category you want a rule for.</flux:text>
            </div>

            <flux:input.group>
                <flux:input
                    wire:model="phrase"
                    wire:keydown.enter.prevent="searchCategories"
                    placeholder="Search for a category..."
                />

                <flux:button type="submit" icon="magnifying-glass" wire:click="searchCategories" />
            </flux:input.group>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                @foreach ($searchedCategories as $index => $category)
                    <div wire:key="searched-category-{{ $category['id'] }}" class="flex flex-col items-center space-y-2">
                        <img
                            width="142"
                            height="190"
                            class="rounded-xl"
                            src="{{ $category['box_art_url'] }}"
                            alt="{{ $category['name'] }}"
                        />

                        <flux:button variant="ghost" size="sm" icon="plus" wire:click="selectCategory({{ $index }})">
                            {{ $category['name'] }}
                        </flux:button>
                    </div>
                @endforeach
            </div>
        @else
            <div>
                <flux:heading size="lg">Add a rule for {{ $ruleForm->category_name }}</flux:heading>
                <flux:text class="mt-2">Choose where to post when you go live in this category.</flux:text>
            </div>

            <flux:checkbox.group wire:model="ruleForm.platforms" label="Post to">
                @foreach ($platforms as $platform)
                    <flux:checkbox :value="$platform->value" :label="$platform->label()" />
                @endforeach
            </flux:checkbox.group>

            <flux:error name="ruleForm.category_id" />
            <flux:error name="ruleForm.platforms.*" />

            <div class="flex my-2">
                <flux:button variant="ghost" size="sm" wire:click="clearSelection">Search again</flux:button>

                <flux:spacer />

                <flux:button type="submit" variant="primary" wire:click="storeRule">Add</flux:button>
            </div>
        @endif
    </flux:modal>
</flux:card>
