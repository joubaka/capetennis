<?php

return [
    'enabled' => env('EVENT_ASSISTANT_ENABLED', true),
    'mode' => env('EVENT_ASSISTANT_MODE', 'local_first'),
    'retention_days' => 30,
    'proposal_minutes' => 15,
    'daily_message_limit' => 100,
    'daily_voice_limit' => 30,
    'transcription' => [
        'provider' => env('EVENT_ASSISTANT_TRANSCRIPTION_PROVIDER', 'gemini'),
        'endpoint' => env('WHISPER_TRANSCRIPTION_URL', 'http://127.0.0.1:8178/v1/audio/transcriptions'),
    ],
];
