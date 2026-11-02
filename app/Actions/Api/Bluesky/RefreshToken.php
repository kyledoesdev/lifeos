<?php

namespace App\Actions\Api\Bluesky;

use App\Enums\ConnectionType;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Support\Facades\Http;

final class RefreshToken
{
    /**
     * Refresh the stored Bluesky session, logging in with the app password from config
     * when there is no session yet or its refresh token has expired. refreshSession
     * rejects a request body, so it is sent without one.
     */
    public function handle(User $user): Connection
    {
        $connection = $user->connections->firstWhere('type', ConnectionType::BLUESKY->value);

        $response = $connection
            ? Http::baseUrl(config('services.bluesky.service'))
                ->withToken($connection->refresh_token)
                ->send('POST', '/xrpc/com.atproto.server.refreshSession')
            : null;

        if (! $response?->successful()) {
            $response = Http::baseUrl(config('services.bluesky.service'))
                ->post('/xrpc/com.atproto.server.createSession', [
                    'identifier' => config('services.bluesky.handle'),
                    'password' => config('services.bluesky.app_password'),
                ])
                ->throw();
        }

        $connection = $user->connections()->updateOrCreate(['type' => ConnectionType::BLUESKY->value], [
            'external_id' => $response->json('did'),
            'token' => $response->json('accessJwt'),
            'refresh_token' => $response->json('refreshJwt'),
        ]);

        $user->unsetRelation('connections');

        return $connection;
    }
}
