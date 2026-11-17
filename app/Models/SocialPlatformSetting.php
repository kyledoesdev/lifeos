<?php

namespace App\Models;

use App\Enums\SocialPlatform;

class SocialPlatformSetting extends Model
{
    protected $table = 'social_platforms';

    protected $fillable = [
        'platform',
        'enabled',
        'live_stream_template',
    ];

    protected $attributes = [
        'enabled' => false,
    ];

    public function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'enabled' => 'boolean',
        ];
    }
}
