<?php

return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', rtrim(env('APP_URL', 'http://localhost:8000'), '/').'/portal/google/callback'),
    ],
    'mbsp_staff' => [
        'url' => env('MBSP_STAFF_API_URL', ''),
        'basic_username' => env('MBSP_STAFF_API_BASIC_USERNAME', ''),
        'basic_password' => env('MBSP_STAFF_API_BASIC_PASSWORD', ''),
        'api_key' => env('MBSP_STAFF_API_KEY', ''),
        'api_token' => env('MBSP_STAFF_API_TOKEN', ''),
        'password_mode' => env('MBSP_STAFF_API_PASSWORD_MODE', 'md5'),
        'ca_bundle' => env('MBSP_STAFF_API_CA_BUNDLE', ''),
    ],

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

];
