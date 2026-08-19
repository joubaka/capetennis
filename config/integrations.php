<?php

return [
    'jta' => [
        'ability' => 'jta-results:read',
        'rate_limit_per_minute' => (int) env('JTA_API_RATE_PER_MINUTE', 60),
        'token_expiration_days' => (int) env('JTA_TOKEN_EXPIRATION_DAYS', 90),
        'require_https' => env('JTA_API_REQUIRE_HTTPS', true),
    ],
];
