<?php

namespace App\Actions\Api\Bluesky;

use App\Contracts\PublishesSocialMediaPosts;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class CreatePost implements PublishesSocialMediaPosts
{
    public function publish(User $user, string $body, ?array $linkCard = null): array
    {
        $connection = (new RefreshToken)->handle($user);

        $record = [
            '$type' => 'app.bsky.feed.post',
            'text' => $body,
            'createdAt' => now()->toIso8601ZuluString(),
            'langs' => ['en'],
            'facets' => $this->linkFacets($body),
        ];

        if ($linkCard) {
            $record['embed'] = $this->externalEmbed($connection, $linkCard);
        }

        $uri = $this->client($connection)
            ->post('/xrpc/com.atproto.repo.createRecord', [
                'repo' => $connection->external_id,
                'collection' => 'app.bsky.feed.post',
                'record' => $record,
            ])
            ->throw()
            ->json('uri');

        return [
            'external_id' => $uri,
            'url' => 'https://bsky.app/profile/'.Str::betweenFirst($uri, 'at://', '/').'/post/'.Str::afterLast($uri, '/'),
        ];
    }

    /**
     * Bluesky does not detect links in plain text, so each one is marked by its UTF-8
     * byte range. preg_match_all reports byte offsets, which is exactly what is needed.
     */
    private function linkFacets(string $text): array
    {
        preg_match_all('#https?://\S+#u', $text, $matches, PREG_OFFSET_CAPTURE);

        return array_map(function (array $match): array {
            $uri = rtrim($match[0], '.,!?;:)');

            return [
                'index' => ['byteStart' => $match[1], 'byteEnd' => $match[1] + strlen($uri)],
                'features' => [['$type' => 'app.bsky.richtext.facet#link', 'uri' => $uri]],
            ];
        }, $matches[0]);
    }

    /**
     * @param  array{uri: string, title: string, description: string, thumbnail: string}  $linkCard
     */
    private function externalEmbed(Connection $connection, array $linkCard): array
    {
        $external = [
            'uri' => $linkCard['uri'],
            'title' => $linkCard['title'],
            'description' => $linkCard['description'],
            'thumb' => $this->uploadThumbnail($connection, $linkCard['thumbnail']),
        ];

        return ['$type' => 'app.bsky.embed.external', 'external' => array_filter($external)];
    }

    /**
     * The image shown on the link card. Twitch redirects to an "offline" placeholder
     * until a stream's first preview is ready, and a missing image should never stop
     * the post, so any problem just leaves the card without one.
     */
    private function uploadThumbnail(Connection $connection, string $url): ?array
    {
        return rescue(function () use ($connection, $url): ?array {
            $image = Http::withoutRedirecting()->get($url);

            if (! $image->ok()) {
                return null;
            }

            return $this->client($connection)
                ->withBody($image->body(), $image->header('Content-Type'))
                ->post('/xrpc/com.atproto.repo.uploadBlob')
                ->throw()
                ->json('blob');
        }, report: false);
    }

    private function client(Connection $connection): PendingRequest
    {
        return Http::baseUrl(config('services.bluesky.service'))->withToken($connection->token);
    }
}
