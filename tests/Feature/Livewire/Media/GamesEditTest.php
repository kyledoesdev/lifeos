<?php

use App\Enums\Media\GameState;
use App\Enums\MediaType;
use App\Livewire\Media\Games\Edit;
use App\Models\Connection;
use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('toggles each column-backed state on and back off', function (GameState $state) {
    $game = Media::factory()->videoGame()->create();

    Livewire::test(Edit::class)
        ->call('toggleState', $game->getKey(), $state->value)
        ->assertOk();

    expect($state->getValue($game->fresh()))->toBeTrue();

    Livewire::test(Edit::class)
        ->call('toggleState', $game->getKey(), $state->value)
        ->assertOk();

    expect($state->getValue($game->fresh()))->toBeFalse();
})->with([
    GameState::Favorite,
    GameState::Active,
    GameState::Backlog,
    GameState::Completed,
]);

it('toggles total_completion inside the data JSON column', function () {
    $game = Media::factory()->videoGame()->create([
        'data' => ['total_completion' => false],
    ]);

    Livewire::test(Edit::class)
        ->call('toggleState', $game->getKey(), GameState::TotalCompletion->value)
        ->assertOk();

    expect($game->fresh()->data['total_completion'])->toBeTrue();

    Livewire::test(Edit::class)
        ->call('toggleState', $game->getKey(), GameState::TotalCompletion->value)
        ->assertOk();

    expect($game->fresh()->data['total_completion'])->toBeFalse();
});

it('rejects unknown state names', function () {
    $game = Media::factory()->videoGame()->create();

    Livewire::test(Edit::class)
        ->call('toggleState', $game->getKey(), 'type')
        ->assertStatus(422);

    expect($game->fresh()->type)->toBe(MediaType::VIDEO_GAME);
});

it('destroys a game', function () {
    $game = Media::factory()->videoGame()->create();

    Livewire::test(Edit::class)
        ->call('destroy', $game->getKey())
        ->assertOk();

    expect(Media::find($game->getKey()))->toBeNull();
});

it('hides games already in the list from the twitch category search', function () {
    $user = User::factory()->create();
    Connection::factory()->twitch()->for($user)->create();
    $this->actingAs($user);

    Media::factory()->videoGame()->create(['media_id' => '27471']);

    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'fresh-twitch-token']),
        'api.twitch.tv/helix/search/categories*' => Http::response(['data' => [
            ['id' => '27471', 'name' => 'Minecraft', 'box_art_url' => 'https://static-cdn.jtvnw.net/ttv-boxart/27471-52x72.jpg'],
            ['id' => '33214', 'name' => 'Fortnite', 'box_art_url' => 'https://static-cdn.jtvnw.net/ttv-boxart/33214-52x72.jpg'],
        ]]),
    ]);

    $component = Livewire::test(Edit::class)
        ->set('phrase', 'craft')
        ->call('searchCategories');

    expect(collect($component->get('searchedGames'))->values()->all())->toBe([[
        'type' => MediaType::VIDEO_GAME->value,
        'media_id' => '33214',
        'name' => 'Fortnite',
        'cover' => 'https://static-cdn.jtvnw.net/ttv-boxart/33214-285x380.jpg',
    ]]);
});
