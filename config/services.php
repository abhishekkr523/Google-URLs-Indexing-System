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

    'google_search_console' => [
        // The verified Search Console property URL (e.g. "https://yoursite.com/"
        // or "sc-domain:yoursite.com"). Used by the URL Inspection API.
        // Leave blank to skip Search Console inspection.
        'site_url' => env('GOOGLE_SEARCH_CONSOLE_SITE_URL', ''),
    ],

    'indexnow' => [
        // A random string you generate once (e.g. use bin2hex(random_bytes(16))).
        // Host a plain text file at https://<your-host>/<api_key>.txt
        // containing ONLY this key value. This only proves control of `host`
        // below — IndexNow still rejects URLs on any other host (HTTP 422),
        // same restriction as Google's Indexing API, just verified differently.
        'api_key'      => env('INDEXNOW_API_KEY', ''),
        // Full URL to the key file hosted on your domain
        'key_file_url' => env('INDEXNOW_KEY_FILE_URL', ''),
        // Your site's hostname (e.g. powerhousethegym.site.je)
        'host'         => env('INDEXNOW_HOST', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?? 'localhost'),
    ],
    'google' => [
        'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        'bridge_url' => env('GOOGLE_BRIDGE_URL', 'https://powerhousethegym.site.je/crawl-bridge'),
    ],
];
