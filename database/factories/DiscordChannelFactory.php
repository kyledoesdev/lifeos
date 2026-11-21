<?php

namespace Database\Factories;

use App\Models\DiscordChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscordChannel>
 */
class DiscordChannelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel_id' => $this->faker->unique()->numberBetween(1_000_000_000_000_000_000, 1_999_999_999_999_999_999),
            'guild_id' => $this->faker->numberBetween(1_000_000_000_000_000_000, 1_999_999_999_999_999_999),
            'name' => $this->faker->slug(2),
            'bot' => 'royalty.beans',
            'backfilled_at' => null,
            'synced_at' => null,
        ];
    }

    public function backfilled(): static
    {
        return $this->state(fn () => [
            'backfilled_at' => now()->subDay(),
            'synced_at' => now()->subDay(),
        ]);
    }
}
