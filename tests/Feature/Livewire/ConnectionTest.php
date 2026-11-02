<?php

use App\Enums\ConnectionType;
use App\Livewire\Connection;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();

    $this->user = User::factory()->create();

    $this->actingAs($this->user);
});

it('lists bluesky on the connections page', function () {
    $this->get(route('connections.edit'))
        ->assertOk()
        ->assertSee('Connect Bluesky');
});

it('connects bluesky with the configured app password', function () {
    fakeBluesky();

    Livewire::test(Connection::class)
        ->call('connectBluesky')
        ->assertSee('Reconnect Bluesky');

    $connection = $this->user->connections()->sole();

    expect($connection->type)->toBe(ConnectionType::BLUESKY->value)
        ->and($connection->external_id)->toBe('did:plc:spacelampsix');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'createSession'));
});

it('keeps bluesky disconnected when bluesky rejects the login', function () {
    fakeBluesky([
        BLUESKY_SERVICE.'/xrpc/com.atproto.server.createSession' => Http::response(['error' => 'AuthenticationRequired'], 401),
    ]);

    Livewire::test(Connection::class)->call('connectBluesky');

    expect($this->user->connections()->count())->toBe(0);
});
