<?php

namespace App\Livewire\Forms;

use App\Enums\SocialPlatform;
use App\Models\StreamCategoryRule;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Form;

class StreamCategoryRuleForm extends Form
{
    public string $category_id = '';

    public string $category_name = '';

    public ?string $box_art_url = null;

    /**
     * @var array<int, string>
     */
    public array $platforms = [];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'string', 'max:255', Rule::unique('stream_category_rules', 'category_id')],
            'category_name' => ['required', 'string', 'max:255'],
            'box_art_url' => ['nullable', 'url', 'max:255'],
            'platforms' => ['array'],
            'platforms.*' => [Rule::enum(SocialPlatform::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Pick a category to add a rule for.',
            'category_id.unique' => 'There is already a rule for this category.',
            'platforms.*' => 'That is not a platform the life os can post to.',
        ];
    }

    /**
     * @param  array{id: string, name: string, box_art_url: string}  $category
     */
    public function fillFromSearch(array $category): void
    {
        $this->category_id = $category['id'];
        $this->category_name = $category['name'];
        $this->box_art_url = $category['box_art_url'];

        $this->resetValidation();
    }

    public function store(): void
    {
        $this->validate();

        StreamCategoryRule::create([
            'category_id' => $this->category_id,
            'category_name' => $this->category_name,
            'box_art_url' => $this->box_art_url,
            'platforms' => $this->platforms,
        ]);

        $this->reset();

        Flux::modal('create-stream-category-rule')->close();

        Flux::toast(variant: 'success', text: 'Rule Added!', duration: 3000);
    }
}
