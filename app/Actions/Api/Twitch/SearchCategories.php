<?php

namespace App\Actions\Api\Twitch;

use App\Enums\ConnectionType;
use App\Enums\MediaType;
use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

final class SearchCategories
{
    public function search(User $user, string $phrase, MediaType $mediaType)
    {
        $games = Media::where('type', $mediaType->value)->pluck('media_id')->toArray();

        return $this->handle($user, $phrase)->map(function ($game) use ($games, $mediaType) {
            if (in_array($game['id'], $games)) {
                return null;
            }

            return [
                'type' => $mediaType->value,
                'media_id' => $game['id'],
                'name' => $game['name'],
                'cover' => $game['box_art_url'],
            ];
        })->filter();
    }

    /**
     * @return Collection<int, array{id: string, name: string, box_art_url: string}>
     */
    public function handle(User $user, string $phrase): Collection
    {
        (new RefreshToken)->handle($user);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$user->connections->firstWhere('type', ConnectionType::TWITCH->value)->token,
            'Content-Type' => 'application/json',
            'Client-Id' => config('services.twitch.client_id'),
        ])->get('https://api.twitch.tv/helix/search/categories', [
            'query' => $phrase,
            'first' => 9,
        ]);

        if (! $response->successful()) {
            return collect();
        }

        return collect($response->json('data'))->map(fn (array $category): array => [
            'id' => $category['id'],
            'name' => $category['name'],
            'box_art_url' => $this->fix_box_art($category['box_art_url']),
        ]);
    }

    /* disgusting hack to get high rez images from this endpoint */
    private function fix_box_art(string $string): string
    {
        return str_replace(['-52', 'x72'], ['-285', 'x380'], $string);
    }
}
