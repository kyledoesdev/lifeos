<?php

use Illuminate\Support\Facades\Http;

const BLUESKY_SERVICE = 'https://bsky.test';

const BLUESKY_POST_URL = 'https://bsky.app/profile/did:plc:spacelampsix/post/3kpost';

/**
 * Configures Bluesky credentials and fakes every endpoint a post touches. Fakes
 * match in the order they were registered, so overrides are placed first.
 *
 * @param  array<string, mixed>  $overrides
 */
function fakeBluesky(array $overrides = []): void
{
    config()->set('services.bluesky.service', BLUESKY_SERVICE);
    config()->set('services.bluesky.handle', 'spacelampsix.bsky.social');
    config()->set('services.bluesky.app_password', 'test-app-password');

    Http::fake($overrides + [
        BLUESKY_SERVICE.'/xrpc/com.atproto.server.createSession' => Http::response(blueskySession()),
        BLUESKY_SERVICE.'/xrpc/com.atproto.server.refreshSession' => Http::response(blueskySession()),
        BLUESKY_SERVICE.'/xrpc/com.atproto.repo.uploadBlob' => Http::response(['blob' => [
            '$type' => 'blob',
            'ref' => ['$link' => 'bafkreithumbnail'],
            'mimeType' => 'image/jpeg',
            'size' => 10,
        ]]),
        BLUESKY_SERVICE.'/xrpc/com.atproto.repo.createRecord' => Http::response([
            'uri' => 'at://did:plc:spacelampsix/app.bsky.feed.post/3kpost',
            'cid' => 'bafyreirecord',
        ]),
        'static-cdn.jtvnw.net/*' => Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']),
    ]);
}

/**
 * Bluesky session tokens run past 255 characters, so the fakes do too.
 *
 * @return array{did: string, handle: string, accessJwt: string, refreshJwt: string}
 */
function blueskySession(string $suffix = 'fresh'): array
{
    return [
        'did' => 'did:plc:spacelampsix',
        'handle' => 'spacelampsix.bsky.social',
        'accessJwt' => str_repeat('a', 400).$suffix,
        'refreshJwt' => str_repeat('r', 400).$suffix,
    ];
}
