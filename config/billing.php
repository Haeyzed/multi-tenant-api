<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Frontend application URL
    |--------------------------------------------------------------------------
    |
    | Used for customer-facing links embedded in billing emails (trial checkout)
    | and as the default host for payment success/cancel redirects.
    |
    */
    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost:3000')),

    /*
    |--------------------------------------------------------------------------
    | Frontend checkout path
    |--------------------------------------------------------------------------
    |
    | Path template on the frontend for signed trial checkout links.
    | {subscription} is replaced with the subscription ID. Query params
    | (expires, signature) from the signed API URL are appended.
    |
    */
    'frontend_checkout_path' => env(
        'BILLING_FRONTEND_CHECKOUT_PATH',
        '/central/billing/checkout/{subscription}'
    ),

    /*
    |--------------------------------------------------------------------------
    | Frontend invoice payment path
    |--------------------------------------------------------------------------
    |
    | Path template on the frontend for signed public invoice payment links.
    | {invoice} is replaced with the invoice ID. Query params (expires,
    | signature) from the signed API URL are appended.
    |
    */
    'frontend_invoice_path' => env(
        'BILLING_FRONTEND_INVOICE_PATH',
        '/central/billing/invoices/{invoice}'
    ),

    /*
    |--------------------------------------------------------------------------
    | Frontend signup card-verification return path
    |--------------------------------------------------------------------------
    */
    'frontend_signup_complete_path' => env(
        'BILLING_FRONTEND_SIGNUP_COMPLETE_PATH',
        '/central/signup/complete/{intent}'
    ),

    'frontend_signup_cancel_path' => env(
        'BILLING_FRONTEND_SIGNUP_CANCEL_PATH',
        '/central/signup'
    ),

    /*
    |--------------------------------------------------------------------------
    | Trial reminder window (days)
    |--------------------------------------------------------------------------
    |
    | How many days before trial_ends_at to send the Trial Ending email.
    |
    */
    'trial_reminder_days' => (int) env('BILLING_TRIAL_REMINDER_DAYS', 3),

    'invoice_due_days' => (int) env('BILLING_INVOICE_DUE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Trial grace period (days)
    |--------------------------------------------------------------------------
    |
    | Grace days applied when a trial ends and the subscription becomes past_due.
    |
    */
    'trial_grace_days' => (int) env('BILLING_TRIAL_GRACE_DAYS', 3),

    'past_due_grace_days' => (int) env(
        'BILLING_PAST_DUE_GRACE_DAYS',
        env('BILLING_TRIAL_GRACE_DAYS', 3),
    ),

    /*
    |--------------------------------------------------------------------------
    | Signed checkout link TTL (hours)
    |--------------------------------------------------------------------------
    |
    | Lifetime of signed public checkout URLs embedded in trial emails.
    |
    */
    'checkout_link_ttl_hours' => (int) env('BILLING_CHECKOUT_LINK_TTL_HOURS', 72),

    /*
    |--------------------------------------------------------------------------
    | Signed invoice payment link TTL (hours)
    |--------------------------------------------------------------------------
    |
    | Lifetime of signed public invoice payment URLs embedded in emails.
    |
    */
    'invoice_link_ttl_hours' => (int) env('BILLING_INVOICE_LINK_TTL_HOURS', 168),

    'default_interval' => env('BILLING_DEFAULT_INTERVAL', 'monthly'),

    'price_fallback_mode' => env('BILLING_PRICE_FALLBACK_MODE', 'any_active'),

    'invoice_number_prefix' => env('INVOICE_NUMBER_PREFIX', 'INV-'),

];
