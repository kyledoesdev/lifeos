<?php

namespace Database\Factories;

use App\Enums\SocialPlatform;
use App\Models\SocialPlatformSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The create_social_platforms migration already inserts a row per platform,
 * so tests update that row through SocialPlatform::settings() instead.
 *
 * @extends Factory<SocialPlatformSetting>
 */
class SocialPlatformSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform' => SocialPlatform::BLUESKY,
            'enabled' => false,
            'live_stream_template' => null,
        ];
    }
}
