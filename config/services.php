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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // TODO: Renseigner ces secrets une fois les contrats signés avec chaque
    // opérateur mobile money. Tant qu'une valeur est vide, WebhookController
    // n'exige pas de signature pour cet opérateur (pratique en développement,
    // à combler avant la mise en production).
    'mobile_money' => [
        'wave' => [
            'webhook_secret' => env('WAVE_WEBHOOK_SECRET'),
        ],
        'orange' => [
            'webhook_secret' => env('ORANGE_WEBHOOK_SECRET'),
        ],
        'mtn' => [
            'webhook_secret' => env('MTN_WEBHOOK_SECRET'),
        ],
        'moov' => [
            'webhook_secret' => env('MOOV_WEBHOOK_SECRET'),
        ],
    ],

];
