<?php

namespace App\Actions\LiveStreams;

use App\Actions\Social\PublishSocialMediaPost;
use App\Enums\SocialPlatform;
use App\Models\LiveStream;
use App\Models\SocialMediaPost;
use App\Models\StreamCategoryRule;
use App\Models\User;
use Illuminate\Support\Str;

final class ShareLiveStream
{
    /**
     * Post the go live announcement to every platform the stream's category rule
     * allows, returning one outcome per platform for the internal updates summary.
     *
     * @return array<string, string>
     */
    public function handle(LiveStream $stream, User $user): array
    {
        $rule = StreamCategoryRule::query()->firstWhere('category_id', $stream->category_id);

        return collect(SocialPlatform::cases())
            ->mapWithKeys(fn (SocialPlatform $platform): array => [
                $platform->label() => match (true) {
                    ! $platform->enabled() => 'skipped (switched off)',
                    ! $platform->isConnectedFor($user) => 'skipped (not connected)',
                    ! $rule?->allows($platform) => "skipped (not enabled for {$stream->category_label})",
                    default => $this->outcome($this->shareTo($stream, $platform, $user)),
                },
            ])
            ->all();
    }

    /**
     * Post to a single platform regardless of its switch or the category rules.
     */
    public function shareTo(LiveStream $stream, SocialPlatform $platform, User $user): SocialMediaPost
    {
        return (new PublishSocialMediaPost)->handle($user, $stream, [
            'platform' => $platform,
            'body' => $this->body($stream, $platform),
            'link_card' => [
                'uri' => $stream->url(),
                'title' => $stream->title_label,
                'description' => "Playing {$stream->category_label} on Twitch",
                'thumbnail' => $stream->thumbnailUrl(),
            ],
        ]);
    }

    private function outcome(SocialMediaPost $post): string
    {
        return $post->wasSent() ? "posted {$post->url}" : "failed: {$post->error}";
    }

    /**
     * The title is the only part long enough to trim, so it gives way when the
     * template would run past the platform's character limit.
     */
    private function body(LiveStream $stream, SocialPlatform $platform): string
    {
        $render = fn (string $title): string => strtr($platform->liveStreamTemplate(), [
            '{title}' => $title,
            '{category}' => $stream->category_label,
            '{url}' => $stream->url(),
        ]);

        $title = $stream->title_label;
        $overflow = Str::length($render($title)) - $platform->characterLimit();

        if ($overflow <= 0) {
            return $render($title);
        }

        return $render(Str::substr($title, 0, max(0, Str::length($title) - $overflow - 1)).'…');
    }
}
