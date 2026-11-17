<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordVoiceMessage extends Model
{
    /**
     * Discord's IS_VOICE_MESSAGE message flag.
     */
    public const int FLAG = 1 << 13;

    protected $fillable = [
        'discord_channel_id',
        'message_id',
        'author_id',
        'author_username',
        'author_display_name',
        'duration_seconds',
        'sent_at',
    ];

    public function casts(): array
    {
        return [
            'message_id' => 'integer',
            'author_id' => 'integer',
            'duration_seconds' => 'float',
            'sent_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(DiscordChannel::class, 'discord_channel_id');
    }

    public function getAuthorNameAttribute(): string
    {
        return $this->author_display_name ?? $this->author_username;
    }
}
