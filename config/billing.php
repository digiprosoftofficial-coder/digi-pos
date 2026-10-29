<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sandbox plan checkout
    |--------------------------------------------------------------------------
    |
    | When enabled, store owners can activate paid plans without a real gateway
    | (intended for local/staging only — disable in production).
    |
    */

    'sandbox_checkout_enabled' => (bool) env('BILLING_SANDBOX_CHECKOUT', env('APP_ENV') !== 'production'),

    /*
    |--------------------------------------------------------------------------
    | Self-serve registration trial length
    |--------------------------------------------------------------------------
    |
    | Used until a super admin saves a value in platform settings.
    |
    */
    'trial_days' => (int) env('STORE_TRIAL_DAYS', 7),

];
