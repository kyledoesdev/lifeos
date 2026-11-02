<?php

namespace App\Contracts;

use App\Models\User;

interface PublishesSocialMediaPosts
{
    /**
     * Throws on failure so the caller can record it against the post.
     */
    public function publish(User $user, string $body, ?array $linkCard = null): array;
}
