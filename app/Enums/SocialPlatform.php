<?php

namespace App\Enums;

use App\Actions\Api\Bluesky\CreatePost;
use App\Contracts\PublishesSocialMediaPosts;
use App\Enums\Concerns\Toggleable;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

enum SocialPlatform: string
{
    use Toggleable;

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

    public function liveStreamTemplate(): string
    {
        return Cache::get("social.{$this->value}.live_stream_template") ?? match ($this) {
            self::BLUESKY => "🔴 I'm live on Twitch!\n\n{title}\nPlaying: {category}\n\n{url}",
        };
    }

    /**
     * Passing null drops the custom template and falls back to the default.
     */
    public function setLiveStreamTemplate(?string $template): void
    {
        if ($template === null) {
            Cache::forget("social.{$this->value}.live_stream_template");

            return;
        }

        Cache::forever("social.{$this->value}.live_stream_template", $template);
    }

    protected function toggleKey(): string
    {
        return "social.{$this->value}.enabled";
    }
}
