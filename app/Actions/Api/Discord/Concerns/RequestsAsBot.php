<?php

namespace App\Actions\Api\Discord\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Works for any bot. The class using it takes that bot's token as `$token`,
 * usually from DiscordChannel::botToken().
 *
 * @property-read string $token
 */
trait RequestsAsBot
{
    private function bot(): PendingRequest
    {
        return Http::baseUrl('https://discord.com/api/v10')
            ->withToken($this->token, 'Bot')
            ->acceptJson()
            ->timeout(15)
            ->retry(
                4,
                fn (int $attempt, Throwable $e): int => $this->retryDelay($attempt, $e),
                fn (Throwable $e): bool => $this->shouldRetry($e),
            );
    }

    /**
     * A 429 says in its body how long to back off for.
     */
    private function retryDelay(int $attempt, Throwable $e): int
    {
        if ($e instanceof RequestException && $e->response->status() === 429) {
            return (int) ceil((float) $e->response->json('retry_after', 1) * 1000) + 100;
        }

        return $attempt * 1000;
    }

    private function shouldRetry(Throwable $e): bool
    {
        return $e instanceof ConnectionException
            || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError()));
    }

    /**
     * Waits for the bucket to refill once it runs dry, so long syncs rarely hit a 429 at all.
     */
    private function respectRateLimit(Response $response): void
    {
        if ($response->header('X-RateLimit-Remaining') !== '0') {
            return;
        }

        Sleep::usleep((int) ceil((float) $response->header('X-RateLimit-Reset-After') * 1_000_000));
    }
}
