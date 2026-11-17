@php($templateRule = $this->categoryRules->firstWhere('id', $categoryTemplateForm->rule_id))

<flux:modal name="edit-stream-category-template" class="space-y-6 md:w-1/2">
    <div>
        <flux:heading size="lg">Go live post for {{ $categoryTemplateForm->category_name }}</flux:heading>
        <flux:text class="mt-2">Leave a platform on its own template, or write one just for this category.</flux:text>
    </div>

    @if ($templateRule)
        @forelse (collect($platforms)->filter(fn ($platform) => $templateRule->allows($platform)) as $platform)
            <div wire:key="category-template-{{ $templateRule->id }}-{{ $platform->value }}" class="space-y-2">
                <flux:textarea
                    wire:model="categoryTemplateForm.templates.{{ $platform->value }}"
                    :label="$platform->label()"
                    :placeholder="$platform->liveStreamTemplate()"
                    rows="6"
                    resize="vertical"
                />

                <flux:text size="sm">
                    @if ($templateRule->templateFor($platform) === null)
                        Using the {{ $platform->label() }} template shown above.
                    @endif
                    Use <code>{title}</code>, <code>{category}</code>, and <code>{url}</code> to fill in the stream's details.
                    Long titles are shortened to fit {{ $platform->characterLimit() }} characters.
                </flux:text>

                <div class="flex">
                    <flux:button variant="ghost" size="sm" wire:click="clearCategoryTemplate('{{ $platform->value }}')">
                        Use {{ $platform->label() }} template
                    </flux:button>

                    <flux:spacer />

                    <flux:button variant="primary" size="sm" wire:click="saveCategoryTemplate('{{ $platform->value }}')">Save</flux:button>
                </div>
            </div>
        @empty
            <flux:text>This rule does not post anywhere yet. Tick a platform for it first.</flux:text>
        @endforelse
    @endif
</flux:modal>
