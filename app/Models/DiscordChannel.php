<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscordChannel extends Model
{
    protected $fillable = [
        'channel_id',
        'guild_id',
        'name',
        'bot',
        'newest_message_id',
        'oldest_message_id',
        'backfilled_at',
        'synced_at',
    ];

    public function casts(): array
    {
        return [
            'channel_id' => 'integer',
            'guild_id' => 'integer',
            'newest_message_id' => 'integer',
            'oldest_message_id' => 'integer',
            'backfilled_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function voiceMessages(): HasMany
    {
        return $this->hasMany(DiscordVoiceMessage::class);
    }

    public function botToken(): ?string
    {
        return config("services.discord.{$this->bot}.bot.token");
    }

    public function getLabelAttribute(): string
    {
        return $this->name ? "#{$this->name}" : "Channel {$this->channel_id}";
    }
}
