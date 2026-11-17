<?php

namespace App\Actions\Api\Discord;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

final class DescribeBotFailure
{
    public function handle(ConnectionException|RequestException $e): string
    {
        if ($e instanceof ConnectionException) {
            return 'Discord could not be reached.';
        }

        return match ($e->response->status()) {
            401 => 'Discord rejected the bot token.',
            403 => 'The bot can not read that channel. It needs View Channel and Read Message History there.',
            404 => 'Discord has no channel with that ID, or the bot is not in its server.',
            default => "Discord answered with HTTP {$e->response->status()}.",
        };
    }
}
