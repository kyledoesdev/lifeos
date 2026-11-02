<?php

namespace App\Livewire\Forms;

use App\Enums\SocialPlatform;
use Flux\Flux;
use Livewire\Form;

class LiveStreamTemplateForm extends Form
{
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
            'templates.*.required' => 'The post template can not be empty.',
            'templates.*.max' => 'Posts can be at most :max characters.',
        ];
    }

    public function fillFromPlatforms(): void
    {
        foreach (SocialPlatform::cases() as $platform) {
            $this->templates[$platform->value] = $platform->liveStreamTemplate();
        }
    }

    public function save(SocialPlatform $platform): void
    {
        $this->validateOnly("templates.{$platform->value}");

        $platform->setLiveStreamTemplate($this->templates[$platform->value]);

        Flux::toast(variant: 'success', text: "{$platform->label()} template saved!", duration: 3000);
    }

    public function restoreDefault(SocialPlatform $platform): void
    {
        $platform->setLiveStreamTemplate(null);

        $this->templates[$platform->value] = $platform->liveStreamTemplate();

        $this->resetValidation("templates.{$platform->value}");

        Flux::toast(variant: 'success', text: "{$platform->label()} template reset to the default.", duration: 3000);
    }
}
