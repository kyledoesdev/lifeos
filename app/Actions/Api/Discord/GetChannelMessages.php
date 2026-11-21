<?php

namespace App\Actions\Api\Discord;

use App\Actions\Api\Discord\Concerns\RequestsAsBot;

final class GetChannelMessages
{
    use RequestsAsBot;

    public const int PAGE_SIZE = 100;

    public function __construct(private string $token) {}

    /**
     * Discord pages by message id and returns each page newest first, whichever
     * direction the cursor points.
     *
     * @param  array{before?: int, after?: int}  $cursor
     * @return list<array<string, mixed>>
     */
    public function handle(int $channelId, array $cursor = []): array
    {
        $response = $this->bot()->get("channels/{$channelId}/messages", [
            'limit' => self::PAGE_SIZE,
            ...$cursor,
        ]);

        $this->respectRateLimit($response);

        return $response->json();
    }
}
