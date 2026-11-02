<?php

namespace Database\Factories;

use App\Enums\ConnectionType;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Connection>
 */
class ConnectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => ConnectionType::TWITCH->value,
            'external_id' => (string) $this->faker->unique()->numberBetween(1, 1_000_000),
            'token' => $this->faker->sha1(),
            'refresh_token' => $this->faker->sha1(),
        ];
    }

    public function twitch(): static
    {
        return $this->state(fn () => ['type' => ConnectionType::TWITCH->value]);
    }

    public function bluesky(): static
    {
        return $this->state(fn () => [
            'type' => ConnectionType::BLUESKY->value,
            'external_id' => 'did:plc:'.$this->faker->regexify('[a-z0-9]{24}'),
        ]);
    }
}
