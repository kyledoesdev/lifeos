<?php

namespace App\Enums;

use App\Actions\Api\Bluesky\CreatePost;
use App\Contracts\PublishesSocialMediaPosts;
use App\Models\SocialPlatformSetting;
use App\Models\User;

enum SocialPlatform: string
{
    case BLUESKY = 'bluesky';

    public function label(): string
    {
        return match ($this) {
            self::BLUESKY => 'Bluesky',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::BLUESKY => 'at-symbol',
        };
    }

    public function characterLimit(): int
    {
        return match ($this) {
            self::BLUESKY => 300,
        };
    }

    public function publisher(): PublishesSocialMediaPosts
    {
        return match ($this) {
            self::BLUESKY => new CreatePost,
        };
    }

    public function isConnectedFor(User $user): bool
    {
        $connectionType = match ($this) {
            self::BLUESKY => ConnectionType::BLUESKY,
        };

        return $user->connections->contains('type', $connectionType->value);
    }

    public function settings(): SocialPlatformSetting
    {
        return SocialPlatformSetting::query()->firstOrNew(['platform' => $this]);
    }

    public function enabled(): bool
    {
        return $this->settings()->enabled;
    }

    /**
     * The platform wide template, used when the stream's category has none of its own.
     */
    public function liveStreamTemplate(): string
    {
        return $this->settings()->live_stream_template ?? $this->defaultLiveStreamTemplate();
    }

    public function defaultLiveStreamTemplate(): string
    {
        return match ($this) {
            self::BLUESKY => "🔴 I'm live on Twitch!\n\n{title}\nPlaying: {category}\n\n{url}",
        };
    }
}
