<?php

return [
    'enabled' => env('EVENT_ASSISTANT_ENABLED', true),
    'mode' => env('EVENT_ASSISTANT_MODE', 'local_first'),
    'retention_days' => 30,
    'proposal_minutes' => 15,
    'daily_message_limit' => 100,
    'daily_voice_limit' => 30,
];
