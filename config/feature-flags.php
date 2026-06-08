<?php

/**
 * Feature Flags Configuration
 *
 * Set environment defaults here. These are overridden by:
 *   1. Admin runtime override (Cache-backed)
 *   2. Per-event override (DB + Cache-backed)
 *
 * .env keys:
 *   FLAG_CANONICAL_ENGINE=false
 *   FLAG_HYBRID_ENGINE=true
 *   FLAG_CLEANUP_TOOLS=true
 *   FLAG_CANONICAL_PROGRESSION=false
 *   FLAG_NEW_STANDINGS=false
 *   FLAG_STRICT_INTEGRITY_MODE=false
 */
return [
    'canonical_engine'      => (bool) env('FLAG_CANONICAL_ENGINE',      false),
    'hybrid_engine'         => (bool) env('FLAG_HYBRID_ENGINE',         true),
    'cleanup_tools'         => (bool) env('FLAG_CLEANUP_TOOLS',         true),
    'canonical_progression' => (bool) env('FLAG_CANONICAL_PROGRESSION', false),
    'new_standings'         => (bool) env('FLAG_NEW_STANDINGS',         false),
    'strict_integrity_mode' => (bool) env('FLAG_STRICT_INTEGRITY_MODE', false),
    // PHASE 1 DOUBLES FOUNDATION — disabled by default, never affects production
    'doubles_foundation'    => (bool) env('FLAG_DOUBLES_FOUNDATION',    false),
];
