<?php

namespace App\Enums;

enum ConnectionType: string
{
    case SPOTIFY = 'spotify';
    case TWITCH = 'twitch';
    case BLUESKY = 'bluesky';

    public function label(): string
    {
        return match ($this) {
            self::SPOTIFY => 'Spotify',
            self::TWITCH => 'Twitch',
            self::BLUESKY => 'Bluesky',
        };
    }
}
