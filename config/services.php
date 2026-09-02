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

    'google_indexing' => [
        // Path to the Google Cloud service account JSON key used to authenticate
        // against the Indexing API. See README.md for how this is provisioned.
        'credentials_path' => base_path(env('GOOGLE_INDEXING_CREDENTIALS_PATH', 'service_account.json')),
        'scope' => 'https://www.googleapis.com/auth/indexing',
        'token_uri' => 'https://oauth2.googleapis.com/token',
        'publish_endpoint' => 'https://indexing.googleapis.com/v3/urlNotifications:publish',
    ],

];
