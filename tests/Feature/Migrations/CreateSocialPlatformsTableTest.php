<?php

use App\Enums\SocialPlatform;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

function socialPlatformsMigration(): Migration
{
    return require database_path('migrations/2026_11_16_144058_create_social_platforms_table.php');
}

it('copies the cached switch and template into the table', function () {
    $migration = socialPlatformsMigration();
    $migration->down();

    Cache::forever('social.bluesky.enabled', true);
    Cache::forever('social.bluesky.live_stream_template', 'Cached {url}');

    $migration->up();

    expect(SocialPlatform::BLUESKY->enabled())->toBeTrue()
        ->and(SocialPlatform::BLUESKY->liveStreamTemplate())->toBe('Cached {url}')
        ->and(Cache::has('social.bluesky.enabled'))->toBeFalse()
        ->and(Cache::has('social.bluesky.live_stream_template'))->toBeFalse();
});

it('starts switched off with the default template when nothing was cached', function () {
    $migration = socialPlatformsMigration();
    $migration->down();
    Cache::flush();

    $migration->up();

    expect(SocialPlatform::BLUESKY->enabled())->toBeFalse()
        ->and(SocialPlatform::BLUESKY->settings()->live_stream_template)->toBeNull();
});

it('puts the values back in the cache when rolled back', function () {
    SocialPlatform::BLUESKY->settings()->fill(['enabled' => true, 'live_stream_template' => 'Kept {url}'])->save();

    socialPlatformsMigration()->down();

    expect(Cache::get('social.bluesky.enabled'))->toBeTrue()
        ->and(Cache::get('social.bluesky.live_stream_template'))->toBe('Kept {url}');

    socialPlatformsMigration()->up();
});
