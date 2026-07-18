<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Mode
    |--------------------------------------------------------------------------
    |
    | "test" calls provider APIs with test/sandbox credentials.
    | "live" calls provider APIs with live credentials.
    | Missing credentials fail with a clear error (no local stub charges).
    |
    */

    'mode' => env('PAYMENTS_MODE', 'test'),

    'default' => env('PAYMENTS_DEFAULT_GATEWAY', 'stripe'),

    'currency' => env('PAYMENTS_CURRENCY', 'NGN'),

    /*
    |--------------------------------------------------------------------------
    | Checkout Redirects
    |--------------------------------------------------------------------------
    */

    'success_url' => env(
        'PAYMENTS_SUCCESS_URL',
        env('FRONTEND_URL', env('APP_URL')).'/central/billing/success?payment={payment}'
    ),
    'cancel_url' => env(
        'PAYMENTS_CANCEL_URL',
        env('FRONTEND_URL', env('APP_URL')).'/central/billing/cancel?payment={payment}'
    ),
    'webhook_base_url' => env('PAYMENTS_WEBHOOK_BASE_URL', env('APP_URL').'/api/v1/webhooks/payments'),

    /*
    |--------------------------------------------------------------------------
    | Provider Credentials
    |--------------------------------------------------------------------------
    */

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'publishable' => env('STRIPE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'api_base' => 'https://api.stripe.com',
    ],

    'paystack' => [
        'secret' => env('PAYSTACK_SECRET'),
        'public' => env('PAYSTACK_PUBLIC'),
        'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
        'api_base' => 'https://api.paystack.co',
    ],

    'flutterwave' => [
        'secret' => env('FLUTTERWAVE_SECRET'),
        'public' => env('FLUTTERWAVE_PUBLIC'),
        'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'),
        'api_base' => 'https://api.flutterwave.com/v3',
    ],

    'paddle' => [
        'api_key' => env('PADDLE_API_KEY'),
        'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),
        'sandbox' => env('PADDLE_SANDBOX', true),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'sandbox' => env('PAYPAL_SANDBOX', true),
    ],

    'lemon_squeezy' => [
        'api_key' => env('LEMON_SQUEEZY_API_KEY'),
        'store_id' => env('LEMON_SQUEEZY_STORE_ID'),
        'webhook_secret' => env('LEMON_SQUEEZY_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Zero-decimal currencies (amount already in major units for Stripe)
    |--------------------------------------------------------------------------
    */

    'zero_decimal_currencies' => [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
        'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider currency support (customer-facing chooser)
    |--------------------------------------------------------------------------
    |
    | ISO 4217 codes each hosted checkout provider can accept. Used to filter
    | payment options on public invoice pages. Empty array = no currencies
    | (provider excluded). Use ["*"] to allow any currency.
    |
    */

    'provider_currencies' => [
        'paystack' => ['NGN', 'GHS', 'ZAR', 'USD'],
        'flutterwave' => [
            'NGN', 'GHS', 'KES', 'UGX', 'TZS', 'ZAR', 'XAF', 'XOF',
            'USD', 'EUR', 'GBP',
        ],
        'stripe' => ['*'],
    ],

];
