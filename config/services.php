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

    // The separate central-service repo (see App\Services\CentralServiceClient
    // and App\Services\CentralServiceBus). base_url is used for the
    // synchronous REST calls; api_key must match central-service's own
    // CENTRAL_SERVICE_API_KEY.
    'central_service' => [
        'base_url' => env('CENTRAL_SERVICE_BASE_URL', 'http://127.0.0.1:8100'),
        'api_key' => env('CENTRAL_SERVICE_API_KEY'),
    ],

];
