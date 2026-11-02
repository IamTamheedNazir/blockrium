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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'dodo' => [
        'api_key' => env('DODO_API_KEY'),
        'webhook_secret' => env('DODO_WEBHOOK_SECRET'),
        'base_url' => env('DODO_BASE_URL', 'https://live.dodopayments.com'),
        'boost_return_url' => env('DODO_BOOST_RETURN_URL', env('APP_URL', 'https://maticlegend.com') . '/boost-payment-return'),
        'verification_payment_url' => env('DODO_VERIFICATION_PAYMENT_URL'),
        'verification_amount' => env('DODO_VERIFICATION_AMOUNT', 5),
        'verification_currency' => env('DODO_VERIFICATION_CURRENCY', 'USD'),
    ],

    'didit' => [
        'api_key' => env('DIDIT_API_KEY'),
        'webhook_secret' => env('DIDIT_WEBHOOK_SECRET'),
        'workflow_id' => env('DIDIT_WORKFLOW_ID'),
        'base_url' => env('DIDIT_BASE_URL', 'https://verification.didit.me/v3'),
        'callback_url' => env('DIDIT_CALLBACK_URL'),
        'environment' => env('DIDIT_ENVIRONMENT', 'live'),
        'monthly_limit' => (int) env('DIDIT_MONTHLY_LIMIT', 500),
        'country_limit' => (int) env('DIDIT_COUNTRY_LIMIT', 100),
    ],

];
