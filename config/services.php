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

    'sola' => [
        'api_key'    => env('SOLA_API_KEY'),
        'merchant_id'=> env('SOLA_MERCHANT_ID'),
        'env'        => env('SOLA_ENV', 'sandbox'), // sandbox | live
        'webhook_secret' => env('SOLA_WEBHOOK_SECRET'),
    ],

    'asana' => [
        'token'        => env('ASANA_TOKEN'),
        'workspace_id' => env('ASANA_WORKSPACE_ID'),
    ],

    'hq_rentals' => [
        'api_key'   => env('HQ_RENTALS_API_KEY'),
        'subdomain' => env('HQ_RENTALS_SUBDOMAIN', 'highrental'),
        'location_id' => env('HQ_RENTALS_LOCATION_ID'),
    ],

    'ccc_one' => [
        'username' => env('CCC_ONE_USERNAME'),
        'password' => env('CCC_ONE_PASSWORD'),
    ],

    'towbook' => [
        // OAuth2 client_credentials — register at https://app.towbook.com/Settings/Integrations
        'client_id'     => env('TOWBOOK_CLIENT_ID'),
        'client_secret' => env('TOWBOOK_CLIENT_SECRET'),
        'company_id'    => env('TOWBOOK_COMPANY_ID'),
        // Fallback for scraping if OAuth not yet enabled
        'username'      => env('TOWBOOK_USERNAME'),
        'password'      => env('TOWBOOK_PASSWORD'),
    ],

    'swoop' => [
        'api_key'   => env('SWOOP_API_KEY'),
        'partner_id'=> env('SWOOP_PARTNER_ID'),
        'env'       => env('SWOOP_ENV', 'sandbox'),
    ],

    'allstate_roadside' => [
        'username' => env('ALLSTATE_ROADSIDE_USERNAME'),
        'password' => env('ALLSTATE_ROADSIDE_PASSWORD'),
        'api_key'  => env('ALLSTATE_ROADSIDE_API_KEY'),
    ],

    'twilio' => [
        'sid'   => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from'  => env('TWILIO_FROM_NUMBER'),
    ],

    'telebroad' => [
        'username' => env('TELEBROAD_USERNAME'),
        'password' => env('TELEBROAD_PASSWORD'),
        'phone_number' => env('TELEBROAD_PHONE_NUMBER'),
        'api_url' => env('TELEBROAD_API_URL', 'https://webserv.telebroad.com/api/teleconsole/rest'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    'credit700' => [
        'api_key' => env('CREDIT700_API_KEY'),
        'api_url' => env('CREDIT700_API_URL', 'https://api.700credit.com/v1'),
    ],

];
