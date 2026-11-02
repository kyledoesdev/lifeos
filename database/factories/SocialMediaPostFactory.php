<?php

namespace Database\Factories;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostStatus;
use App\Models\LiveStream;
use App\Models\SocialMediaPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialMediaPost>
 */
class SocialMediaPostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $recordKey = $this->faker->unique()->regexify('[a-z0-9]{13}');

        return [
            'external_id' => "at://did:plc:test/app.bsky.feed.post/{$recordKey}",
            'postable_id' => LiveStream::factory(),
            'postable_type' => (new LiveStream)->getMorphClass(),
            'platform' => SocialPlatform::BLUESKY,
            'body' => $this->faker->sentence(),
            'status' => SocialPostStatus::SENT,
            'url' => "https://bsky.app/profile/did:plc:test/post/{$recordKey}",
            'error' => null,
            'posted_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'external_id' => null,
            'status' => SocialPostStatus::FAILED,
            'url' => null,
            'error' => 'HTTP request returned status code 500',
            'posted_at' => null,
        ]);
    }
}
