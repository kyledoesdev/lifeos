<?php

namespace Database\Factories;

use App\Enums\SocialPlatform;
use App\Models\StreamCategoryRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StreamCategoryRule>
 */
class StreamCategoryRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => (string) $this->faker->unique()->numberBetween(1, 1_000_000),
            'category_name' => $this->faker->words(2, true),
            'box_art_url' => $this->faker->imageUrl(),
            'platforms' => [SocialPlatform::BLUESKY],
        ];
    }

    public function minecraft(): static
    {
        return $this->state(fn () => [
            'category_id' => '27471',
            'category_name' => 'Minecraft',
        ]);
    }

    public function withoutPlatforms(): static
    {
        return $this->state(fn () => ['platforms' => []]);
    }
}
