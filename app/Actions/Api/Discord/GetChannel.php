<?php

namespace App\Actions\Api\Discord;

use App\Actions\Api\Discord\Concerns\RequestsAsBot;

final class GetChannel
{
    use RequestsAsBot;

    public function __construct(private string $token) {}

    /**
     * @return array{id: string, name?: string, guild_id?: string}
     */
    public function handle(int $channelId): array
    {
        $response = $this->bot()->get("channels/{$channelId}");

        $this->respectRateLimit($response);

        return $response->json();
    }
}
