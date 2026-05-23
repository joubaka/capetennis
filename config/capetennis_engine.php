<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cape Tennis Draw Engine Mode
    |--------------------------------------------------------------------------
    |
    | Controls which engine processes draw generation, fixture progression,
    | standings calculation, and bracket rendering.
    |
    | Modes:
    |   legacy    – Use legacy engine only. Canonical services are never called.
    |   hybrid    – Use canonical engine for production. Run legacy in parallel,
    |               compare outputs, and log any mismatches. Production-safe:
    |               canonical errors fall back to legacy automatically.
    |   canonical – Use canonical engine only. Legacy code paths are bypassed.
    |
    | Set via environment variable: DRAW_ENGINE_MODE=hybrid
    |
    */
    'mode' => env('DRAW_ENGINE_MODE', 'hybrid'),

    /*
    |--------------------------------------------------------------------------
    | Comparison logging
    |--------------------------------------------------------------------------
    |
    | In hybrid mode, mismatches between legacy and canonical output are logged
    | to this channel at the specified log level. Set to null to use the default
    | application log channel.
    |
    */
    'comparison_log_channel' => env('DRAW_ENGINE_LOG_CHANNEL', null),
    'comparison_log_level'   => env('DRAW_ENGINE_LOG_LEVEL', 'warning'),

    /*
    |--------------------------------------------------------------------------
    | Fallback behaviour
    |--------------------------------------------------------------------------
    |
    | When canonical engine throws in hybrid or canonical mode:
    |   auto_fallback  – true  → silently fall back to legacy and log critical
    |   auto_fallback  – false → let the exception propagate
    |
    | Recommended: true for hybrid, false for canonical.
    |
    */
    'auto_fallback' => env('DRAW_ENGINE_AUTO_FALLBACK', true),

    /*
    |--------------------------------------------------------------------------
    | Mismatch auto-rollback threshold
    |--------------------------------------------------------------------------
    |
    | If the mismatch rate (mismatches / total runs) for a draw within the last
    | 24 hours exceeds this ratio, EngineRouter will automatically downgrade
    | the router to 'hybrid' mode and log an ENGINE_FALLBACK audit event.
    |
    | Set to 0 to disable automatic rollback.
    |
    */
    'mismatch_rollback_threshold' => env('DRAW_ENGINE_MISMATCH_THRESHOLD', 25),

    /*
    |--------------------------------------------------------------------------
    | Debug panel auth guard
    |--------------------------------------------------------------------------
    |
    | The middleware guard applied to the engine debug panel route.
    |
    */
    'debug_panel_middleware' => ['web', 'auth'],

];
