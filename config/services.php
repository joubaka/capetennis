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
  'payfast' => [
    'merchant_id'        => env('PAYFAST_MERCHANT_ID'),
    'merchant_key'       => env('PAYFAST_MERCHANT_KEY'),
    'sandbox_id'         => env('PAYFAST_SANDBOX_ID'),
    'sandbox_key'        => env('PAYFAST_SANDBOX_KEY'),
    'passphrase'         => env('PAYFAST_PASSPHRASE'),
    'passphrase_live'    => env('PAYFAST_PASSPHRASE_LIVE'),
    'passphrase_sandbox' => env('PAYFAST_PASSPHRASE_SANDBOX'),
    'sandbox'            => env('PAYFAST_SANDBOX', false),
    'live_url'           => env('PAYFAST_LIVE_URL', 'https://www.payfast.co.za/eng/process'),
    'sandbox_url'        => env('PAYFAST_SANDBOX_URL', 'https://sandbox.payfast.co.za/eng/process'),
    'live_merchant_id'   => env('PAYFAST_LIVE_MERCHANT_ID'),
    'live_merchant_key'  => env('PAYFAST_LIVE_MERCHANT_KEY'),
    'sandbox_merchant_id'=> env('PAYFAST_SANDBOX_MERCHANT_ID'),
    'sandbox_merchant_key'=> env('PAYFAST_SANDBOX_MERCHANT_KEY'),
    'notify_url'         => env('PAYFAST_NOTIFY_URL', '/notify'),
    'notify_team_url'    => env('PAYFAST_NOTIFY_TEAM_URL', '/notify_team'),
    'cancel_url'         => env('PAYFAST_CANCEL_URL', '/cancel'),
    'return_url'         => env('PAYFAST_RETURN_URL', '/'),
  ],
  'payment_failure_alert' => [
    'enabled' => env('PAYMENT_FAILURE_ALERT_ENABLED', true),
    'recipient' => env('PAYMENT_FAILURE_ALERT_EMAIL', 'hermanustennisacademy@gmail.com'),
  ],

];
