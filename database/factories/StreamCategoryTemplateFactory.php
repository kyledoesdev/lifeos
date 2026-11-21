<?php

namespace Database\Factories;

use App\Enums\SocialPlatform;
use App\Models\StreamCategoryRule;
use App\Models\StreamCategoryTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StreamCategoryTemplate>
 */
class StreamCategoryTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stream_category_rule_id' => StreamCategoryRule::factory(),
            'platform' => SocialPlatform::BLUESKY,
            'template' => "{category} time!\n\n{title}\n\n{url}",
        ];
    }
}
