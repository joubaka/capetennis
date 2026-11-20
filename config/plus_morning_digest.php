<?php

return [
    // Activation and live transport require a separately authorized release operation.
    'delivery_enabled' => env('PLUS_MORNING_DIGEST_ENABLED', false),
];
