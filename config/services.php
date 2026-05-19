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
  ],

];
