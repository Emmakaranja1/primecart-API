<?php

return [
    'mpesa' => [
        'consumer_key' => env('MPESA_CONSUMER_KEY'),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
        'shortcode' => env('MPESA_SHORTCODE'),
        'passkey' => env('MPESA_PASSKEY'),
        'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),
    ],

    'flutterwave' => [
        'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
        'environment' => env('FLUTTERWAVE_ENVIRONMENT', 'sandbox'),
    ],

    'dpo' => [
        'company_token' => env('DPO_COMPANY_TOKEN'),
        'api_key' => env('DPO_API_KEY'),
        'service_type' => env('DPO_SERVICE_TYPE'),
        'environment' => env('DPO_ENVIRONMENT', 'sandbox'),
    ],

    'pesapal' => [
        'consumer_key' => env('PESAPAL_CONSUMER_KEY'),
        'consumer_secret' => env('PESAPAL_CONSUMER_SECRET'),
        'environment' => env('PESAPAL_ENVIRONMENT', 'sandbox'),
    ],
];