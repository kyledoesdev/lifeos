<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Spotify\Provider as SpotifyProvider;
use SocialiteProviders\Twitch\Provider as TwitchProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('spotify', SpotifyProvider::class);
            $event->extendSocialite('twitch', TwitchProvider::class);
        });

        Model::automaticallyEagerLoadRelationships();

        $this->configureDevTerminal();
    }

    private function configureDevTerminal(): void
    {
        if (! $this->app->runningInConsole() || $this->app->runningUnitTests() || $this->app->isProduction()) {
            return;
        }

        DevCommands::except('server', 'logs');
        DevCommands::node('dev', 'vite')->yellow();
        DevCommands::artisan('queue:listen --tries=1 --timeout=0', 'queue')->purple();
    }
}
