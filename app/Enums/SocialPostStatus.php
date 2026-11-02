<?php

namespace App\Enums;

enum SocialPostStatus: string
{
    case SENT = 'sent';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::SENT => 'Posted',
            self::FAILED => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SENT => 'green',
            self::FAILED => 'red',
        };
    }
}
