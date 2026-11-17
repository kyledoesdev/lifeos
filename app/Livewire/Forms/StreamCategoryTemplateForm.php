<?php

namespace App\Livewire\Forms;

use App\Enums\SocialPlatform;
use App\Models\StreamCategoryRule;
use App\Models\StreamCategoryTemplate;
use Flux\Flux;
use Livewire\Form;

class StreamCategoryTemplateForm extends Form
{
    public ?int $rule_id = null;

    public string $category_name = '';

    /**
     * @var array<string, string>
     */
    public array $templates = [];

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return collect(SocialPlatform::cases())
            ->mapWithKeys(fn (SocialPlatform $platform): array => [
                "templates.{$platform->value}" => ['required', 'string', "max:{$platform->characterLimit()}"],
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'templates.*.required' => 'The post template can not be empty. Use the platform template instead to clear it.',
            'templates.*.max' => 'Posts can be at most :max characters.',
        ];
    }

    public function fillFromRule(StreamCategoryRule $rule): void
    {
        $this->resetValidation();

        $this->rule_id = $rule->id;
        $this->category_name = $rule->category_name;

        foreach (SocialPlatform::cases() as $platform) {
            $this->templates[$platform->value] = $rule->templateFor($platform) ?? '';
        }
    }

    public function save(SocialPlatform $platform): void
    {
        $this->validateOnly("templates.{$platform->value}");

        StreamCategoryTemplate::query()->updateOrCreate(
            ['stream_category_rule_id' => $this->rule()->id, 'platform' => $platform],
            ['template' => $this->templates[$platform->value]],
        );

        Flux::toast(variant: 'success', text: "{$this->category_name} {$platform->label()} template saved!", duration: 3000);
    }

    public function clear(SocialPlatform $platform): void
    {
        $this->rule()->templates()->where('platform', $platform)->delete();

        $this->templates[$platform->value] = '';

        $this->resetValidation("templates.{$platform->value}");

        Flux::toast(variant: 'success', text: "{$this->category_name} now uses the {$platform->label()} template.", duration: 3000);
    }

    private function rule(): StreamCategoryRule
    {
        return StreamCategoryRule::findOrFail($this->rule_id);
    }
}
