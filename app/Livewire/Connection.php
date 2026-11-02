<?php

namespace App\Livewire;

use App\Actions\Api\Bluesky\RefreshToken;
use Flux\Flux;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Livewire\Component;

class Connection extends Component
{
    public function render()
    {
        return view('livewire.connection');
    }

    /**
     * Bluesky app passwords skip OAuth, so connecting logs in with the credentials
     * from config instead of redirecting through Socialite.
     */
    public function connectBluesky(): void
    {
        try {
            (new RefreshToken)->handle(auth()->user());
        } catch (ConnectionException|RequestException $e) {
            Flux::toast(variant: 'danger', text: "Could not connect Bluesky: {$e->getMessage()}", duration: 5000);

            return;
        }

        Flux::toast(variant: 'success', text: 'Bluesky connected!', duration: 3000);
    }
}
