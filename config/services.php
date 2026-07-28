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

    'delivery' => [
        'allow_unsigned_local' => env('ALLOW_UNSIGNED_LOCAL_WEBHOOKS', false),
        'webhook_secrets' => [
            'trendyol' => env('TRENDYOL_GO_WEBHOOK_SECRET'),
            'yemeksepeti' => env('YEMEKSEPETI_WEBHOOK_SECRET'),
        ],
    ],

    'updates' => [
        'signing_private_key' => env('UPDATE_SIGNING_PRIVATE_KEY'),
        'signing_public_key' => env('UPDATE_SIGNING_PUBLIC_KEY'),
    ],

    'adisyon' => [
        'base_url' => env('ADISYON_BASE_URL', 'https://adisyon.synaptropic.com'),
        'api_url' => env('ADISYON_SYNC_PULL_URL', 'https://adisyon.synaptropic.com/api/v1/sync/pull'),
        'push_url' => env('ADISYON_SYNC_PUSH_URL', 'https://adisyon.synaptropic.com/api/v1/sync/push'),
        'api_key' => env('ADISYON_DEVICE_API_KEY'),
    ],

];
