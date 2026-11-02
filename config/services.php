<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'spotify' => [
        'client_id' => env('SPOTIFY_CLIENT_ID'),
        'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
        'redirect' => env('CONNECTION_REDIRECT'),
    ],

    'twitch' => [
        'client_id' => env('TWITCH_CLIENT_ID'),
        'client_secret' => env('TWITCH_CLIENT_SECRET'),
        'redirect' => env('CONNECTION_REDIRECT'),
        'channel' => env('TWITCH_CHANNEL', 'spacelampsix'),
    ],

    'bluesky' => [
        'service' => env('BLUESKY_SERVICE', 'https://bsky.social'),
        'handle' => env('BLUESKY_HANDLE'),
        'app_password' => env('BLUESKY_APP_PASSWORD'),
    ],

    'movie_db' => [
        'api_key' => env('MOVIEDB_API_KEY'),
        'access_token' => env('MOVIEDB_KEY'),
    ],

    'geocodio' => [
        'api_key' => env('GEOCODIO_API_KEY'),
    ],

    'discord' => [
        'royalty' => [
            'beans' => [
                'webhook_url' => env('DISCORD_ROYALTY_BEANS_WEBHOOK_URL'),
            ],
            'weather-report' => [
                'webhook_url' => env('DISCORD_ROYALTY_WEATHERREPORT_WEBHOOK_URL'),
            ],
            'roles' => [
                'beans' => env('DISCORD_ROYALTY_BEANS_ROLE_ID'),
            ],
        ],
        'song-rank' => [
            'live-now' => [
                'webhook_url' => env('DISCORD_SONGRANK_LIVENOW_WEBHOOK_URL'),
            ],
            'internal-updates' => [
                'webhook_url' => env('DISCORD_SONGRANK_INTERNALUPDATES_WEBHOOK_URL'),
            ],
        ],
    ],
];
