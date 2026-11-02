<?php

return [
    'enabled' => env('CAPESTER_ENABLED', false),
    'auto_handoff' => [
        'enabled' => env('CAPESTER_AUTO_HANDOFF_ENABLED', false),
        'user_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('CAPESTER_AUTO_HANDOFF_USER_IDS', ''))),
            fn ($id) => preg_match('/^[1-9][0-9]*$/D', $id))),
    ],
    'receiver' => [
        'base_url' => env('CAPESTER_BASE_URL', ''),
        'token' => env('CAPESTER_RECEIVER_TOKEN', ''),
    ],
];
