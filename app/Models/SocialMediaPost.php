<?php

namespace App\Models;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostStatus;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SocialMediaPost extends Model
{
    protected $fillable = [
        'external_id',
        'postable_id',
        'postable_type',
        'platform',
        'body',
        'status',
        'url',
        'error',
        'posted_at',
    ];

    public function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'status' => SocialPostStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    public function postable(): MorphTo
    {
        return $this->morphTo();
    }

    public function wasSent(): bool
    {
        return $this->status === SocialPostStatus::SENT;
    }
}
