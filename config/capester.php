<?php

return [
    'enabled' => env('CAPESTER_ENABLED', false),
    'receiver' => [
        'base_url' => env('CAPESTER_BASE_URL', ''),
        'token' => env('CAPESTER_RECEIVER_TOKEN', ''),
    ],
];
