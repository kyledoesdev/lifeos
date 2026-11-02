<?php

namespace App\Enums\Discord;

use App\Enums\Concerns\Toggleable;

/**
 * Whether a scheduled Discord message mentions the Beans role.
 */
enum PingBeansRole: string
{
    use Toggleable;

    case WEATHER_REPORT = 'discord.ping_beans_role.weather_report';

    protected function toggleKey(): string
    {
        return $this->value;
    }
}
