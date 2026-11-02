<?php

namespace App\Actions\Social;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostStatus;
use App\Models\SocialMediaPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class PublishSocialMediaPost
{
    /**
     * Post about any model and record how it went. A model is only ever posted once
     * per platform, so a sent post is returned as is and a failed one is retried.
     *
     * @param  array{platform: SocialPlatform, body: string, link_card?: array{uri: string, title: string, description: string, thumbnail: string}}  $attributes
     */
    public function handle(User $user, Model $postable, array $attributes): SocialMediaPost
    {
        $post = SocialMediaPost::query()->firstOrNew([
            'postable_id' => $postable->getKey(),
            'postable_type' => $postable->getMorphClass(),
            'platform' => $attributes['platform'],
        ]);

        if ($post->wasSent()) {
            return $post;
        }

        $post->body = $attributes['body'];

        try {
            $published = $attributes['platform']->publisher()->publish($user, $attributes['body'], $attributes['link_card'] ?? null);

            $post->fill([
                'external_id' => $published['external_id'],
                'status' => SocialPostStatus::SENT,
                'url' => $published['url'],
                'error' => null,
                'posted_at' => now(),
            ]);
        } catch (Throwable $e) {
            $post->fill([
                'status' => SocialPostStatus::FAILED,
                'error' => $e->getMessage(),
            ]);
        }

        $post->save();

        return $post;
    }
}
