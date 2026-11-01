<?php

return [
    'enabled' => (bool) env('BOUTIK_MOBILE_MONEY_ENABLED', false),
    'provider' => env('BOUTIK_MOBILE_MONEY_PROVIDER') ?: 'fedapay',
    'currency' => env('BOUTIK_MOBILE_MONEY_CURRENCY', 'XOF'),
    'timeout' => (int) env('BOUTIK_MOBILE_MONEY_TIMEOUT', 15),

    'providers' => [
        'fedapay' => [
            'environment' => env('FEDAPAY_ENVIRONMENT', 'sandbox'),
            'secret_key' => env('FEDAPAY_SECRET_KEY'),
            'webhook_secret' => env('FEDAPAY_WEBHOOK_SECRET'),
        ],

        'mtn' => [
            'base_url' => env('MTN_MOMO_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
            'target_environment' => env('MTN_MOMO_TARGET_ENVIRONMENT', 'sandbox'),
            'subscription_key' => env('MTN_MOMO_SUBSCRIPTION_KEY'),
            'api_user' => env('MTN_MOMO_API_USER'),
            'api_key' => env('MTN_MOMO_API_KEY'),
            'callback_url' => env('MTN_MOMO_CALLBACK_URL'),
            'webhook_secret' => env('MTN_MOMO_WEBHOOK_SECRET'),
        ],

        'moov' => [
            'base_url' => env('MOOV_MONEY_BASE_URL'),
            'client_id' => env('MOOV_MONEY_CLIENT_ID'),
            'client_secret' => env('MOOV_MONEY_CLIENT_SECRET'),
            'api_key' => env('MOOV_MONEY_API_KEY'),
            'merchant_id' => env('MOOV_MONEY_MERCHANT_ID'),
            'callback_url' => env('MOOV_MONEY_CALLBACK_URL'),
            'webhook_secret' => env('MOOV_MONEY_WEBHOOK_SECRET'),
        ],
    ],
];
