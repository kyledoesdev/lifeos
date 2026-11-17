<?php

namespace Database\Factories;

use App\Models\DiscordChannel;
use App\Models\DiscordVoiceMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscordVoiceMessage>
 */
class DiscordVoiceMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $username = $this->faker->unique()->userName();

        return [
            'discord_channel_id' => DiscordChannel::factory(),
            'message_id' => $this->faker->unique()->numberBetween(1_000_000_000_000_000_000, 1_999_999_999_999_999_999),
            'author_id' => $this->faker->numberBetween(100_000_000_000_000_000, 999_999_999_999_999_999),
            'author_username' => $username,
            'author_display_name' => ucfirst($username),
            'duration_seconds' => $this->faker->randomFloat(2, 2, 300),
            'sent_at' => $this->faker->dateTimeBetween('-1 year'),
        ];
    }

    public function by(int $authorId, string $username, ?string $displayName = null): static
    {
        return $this->state(fn () => [
            'author_id' => $authorId,
            'author_username' => $username,
            'author_display_name' => $displayName,
        ]);
    }

    public function lasting(?float $seconds): static
    {
        return $this->state(fn () => ['duration_seconds' => $seconds]);
    }
}
