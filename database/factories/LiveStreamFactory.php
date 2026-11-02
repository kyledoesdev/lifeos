<?php

namespace Database\Factories;

use App\Models\LiveStream;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveStream>
 */
class LiveStreamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => (string) $this->faker->unique()->numberBetween(10_000_000_000, 99_999_999_999),
            'title' => $this->faker->sentence(),
            'category_id' => '27471',
            'category_name' => 'Minecraft',
            'started_at' => now()->subHour(),
            'ended_at' => null,
            'discord_notified_at' => now()->subHour(),
        ];
    }

    /**
     * The end time resolves lazily so it follows a started_at passed to create().
     */
    public function ended(): static
    {
        return $this->state(fn () => [
            'ended_at' => fn (array $attributes) => Carbon::parse($attributes['started_at'])->addHours(3),
        ]);
    }

    public function softwareDevelopment(): static
    {
        return $this->state(fn () => [
            'category_id' => '1469308723',
            'category_name' => 'Software and Game Development',
        ]);
    }

    /**
     * Streams recorded before stream details were kept.
     */
    public function legacy(): static
    {
        return $this->state(fn () => [
            'external_id' => null,
            'title' => null,
            'category_id' => null,
            'category_name' => null,
        ]);
    }
}
