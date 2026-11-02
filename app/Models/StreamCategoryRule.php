<?php

namespace App\Models;

use App\Enums\SocialPlatform;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;

/**
 * Which platforms a go live post goes to when streaming a Twitch category.
 */
class StreamCategoryRule extends Model
{
    protected $fillable = [
        'category_id',
        'category_name',
        'box_art_url',
        'platforms',
    ];

    public function casts(): array
    {
        return [
            'platforms' => AsEnumCollection::of(SocialPlatform::class),
        ];
    }

    public function allows(SocialPlatform $platform): bool
    {
        return $this->platforms->contains($platform);
    }

    public function togglePlatform(SocialPlatform $platform): void
    {
        $this->platforms = $this->allows($platform)
            ? $this->platforms->reject(fn (SocialPlatform $allowed): bool => $allowed === $platform)->values()
            : $this->platforms->push($platform);

        $this->save();
    }
}
