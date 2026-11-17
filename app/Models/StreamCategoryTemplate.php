<?php

namespace App\Models;

use App\Enums\SocialPlatform;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamCategoryTemplate extends Model
{
    protected $fillable = [
        'stream_category_rule_id',
        'platform',
        'template',
    ];

    public function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(StreamCategoryRule::class, 'stream_category_rule_id');
    }
}
