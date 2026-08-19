<?php

namespace App\Enums\Discord;

use Illuminate\Support\Facades\Cache;

/**
 * Whether a scheduled Discord message mentions the Beans role. The case value is
 * the cache key, so the toggle survives a deploy without needing a table.
 */
enum PingBeansRole: string
{
    case WEATHER_REPORT = 'discord.ping_beans_role.weather_report';

    /**
     * Clearing the cache leaves the role unpinged rather than surprising the server.
     */
    public function enabled(): bool
    {
        return (bool) Cache::get($this->value, false);
    }

    public function set(bool $enabled): void
    {
        Cache::forever($this->value, $enabled);
    }
}
