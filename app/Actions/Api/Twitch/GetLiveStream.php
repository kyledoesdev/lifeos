<?php

namespace App\Actions\Api\Twitch;

use Illuminate\Support\Facades\Http;

final class GetLiveStream
{
    /**
     * Helix only returns a stream while the channel is live.
     */
    public function handle(string $token): ?array
    {
        $response = Http::withHeaders([
            'Client-ID' => config('services.twitch.client_id'),
            'Authorization' => 'Bearer '.$token,
        ])->get('https://api.twitch.tv/helix/streams', [
            'user_login' => config('services.twitch.channel'),
        ]);

        return $response->throw()->json('data.0');
    }
}
