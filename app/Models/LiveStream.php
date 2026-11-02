<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphMany;

class LiveStream extends Model
{
    protected $fillable = [
        'external_id',
        'title',
        'category_id',
        'category_name',
        'started_at',
        'ended_at',
        'discord_notified_at',
    ];

    public function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'discord_notified_at' => 'datetime',
        ];
    }

    public function socialMediaPosts(): MorphMany
    {
        return $this->morphMany(SocialMediaPost::class, 'postable');
    }

    public function isLive(): bool
    {
        return $this->external_id !== null && $this->ended_at === null;
    }

    public function url(): string
    {
        return 'https://twitch.tv/'.config('services.twitch.channel');
    }

    /**
     * The same preview Helix returns as thumbnail_url, sized for a link card.
     */
    public function thumbnailUrl(): string
    {
        return 'https://static-cdn.jtvnw.net/previews-ttv/live_user_'.config('services.twitch.channel').'-1280x720.jpg';
    }

    public function getTitleLabelAttribute(): string
    {
        return $this->title ?? 'Untitled Stream';
    }

    public function getCategoryLabelAttribute(): string
    {
        return $this->category_name ?? 'Unknown Category';
    }

    public function getDurationAttribute(): ?string
    {
        return $this->ended_at ? $this->started_at->diff($this->ended_at)->forHumans(parts: 2) : null;
    }
}
