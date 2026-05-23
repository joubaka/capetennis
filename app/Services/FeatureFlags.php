<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FeatureFlags
 *
 * Centralized feature flag resolution with a three-layer priority:
 *   1. Per-event override (stored in DB / cache)
 *   2. Admin/runtime override (stored in cache)
 *   3. Environment default (config/feature-flags.php → .env)
 *
 * Usage:
 *   FeatureFlags::enabled('canonical_engine')
 *   FeatureFlags::enabled('canonical_engine', eventId: 42)
 *   FeatureFlags::enable('cleanup_tools')        // admin runtime override
 *   FeatureFlags::disable('strict_integrity_mode')
 *   FeatureFlags::setForEvent(42, 'canonical_engine', true)
 */
class FeatureFlags
{
    // ------------------------------------------------------------------
    // Canonical flag names
    // ------------------------------------------------------------------
    public const CANONICAL_ENGINE      = 'canonical_engine';
    public const HYBRID_ENGINE         = 'hybrid_engine';
    public const CLEANUP_TOOLS         = 'cleanup_tools';
    public const CANONICAL_PROGRESSION = 'canonical_progression';
    public const NEW_STANDINGS         = 'new_standings';
    public const STRICT_INTEGRITY_MODE = 'strict_integrity_mode';

    public const ALL_FLAGS = [
        self::CANONICAL_ENGINE,
        self::HYBRID_ENGINE,
        self::CLEANUP_TOOLS,
        self::CANONICAL_PROGRESSION,
        self::NEW_STANDINGS,
        self::STRICT_INTEGRITY_MODE,
    ];

    private const CACHE_PREFIX     = 'feature_flag.';
    private const EVENT_CACHE_TTL  = 600;  // 10 min
    private const ADMIN_CACHE_TTL  = 3600; // 1 hr

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Check if a flag is enabled.
     * Respects the three-layer priority: event → admin → env.
     */
    public static function enabled(string $flag, ?int $eventId = null): bool
    {
        // 1. Per-event override
        if ($eventId !== null) {
            $eventValue = self::getEventOverride($flag, $eventId);
            if ($eventValue !== null) {
                return (bool) $eventValue;
            }
        }

        // 2. Admin/runtime override (cache)
        $adminValue = Cache::get(self::CACHE_PREFIX . 'admin.' . $flag);
        if ($adminValue !== null) {
            return (bool) $adminValue;
        }

        // 3. Environment default
        return (bool) config('feature-flags.' . $flag, false);
    }

    /**
     * Enable a flag via admin runtime override.
     */
    public static function enable(string $flag): void
    {
        Cache::put(self::CACHE_PREFIX . 'admin.' . $flag, true, self::ADMIN_CACHE_TTL);
        Log::info('[FeatureFlags] Flag enabled via admin override', ['flag' => $flag]);
    }

    /**
     * Disable a flag via admin runtime override.
     */
    public static function disable(string $flag): void
    {
        Cache::put(self::CACHE_PREFIX . 'admin.' . $flag, false, self::ADMIN_CACHE_TTL);
        Log::info('[FeatureFlags] Flag disabled via admin override', ['flag' => $flag]);
    }

    /**
     * Clear an admin override (revert to env default).
     */
    public static function clearOverride(string $flag): void
    {
        Cache::forget(self::CACHE_PREFIX . 'admin.' . $flag);
    }

    /**
     * Set a per-event flag override.
     */
    public static function setForEvent(int $eventId, string $flag, bool $value): void
    {
        $cacheKey = self::CACHE_PREFIX . "event.{$eventId}.{$flag}";
        Cache::put($cacheKey, $value, self::EVENT_CACHE_TTL);

        try {
            DB::table('feature_flag_event_overrides')->upsert(
                ['event_id' => $eventId, 'flag' => $flag, 'enabled' => $value, 'updated_at' => now()],
                ['event_id', 'flag'],
                ['enabled', 'updated_at']
            );
        } catch (\Throwable $e) {
            Log::warning('[FeatureFlags] Could not persist event override to DB', [
                'event_id' => $eventId, 'flag' => $flag, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear a per-event flag override.
     */
    public static function clearForEvent(int $eventId, string $flag): void
    {
        Cache::forget(self::CACHE_PREFIX . "event.{$eventId}.{$flag}");
        try {
            DB::table('feature_flag_event_overrides')
                ->where('event_id', $eventId)->where('flag', $flag)->delete();
        } catch (\Throwable $e) {
            // silently ignore
        }
    }

    /**
     * Return all flag states (for dashboard display).
     */
    public static function all(?int $eventId = null): array
    {
        return collect(self::ALL_FLAGS)->mapWithKeys(function (string $flag) use ($eventId) {
            return [$flag => self::enabled($flag, $eventId)];
        })->all();
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    private static function getEventOverride(string $flag, int $eventId): ?bool
    {
        $cacheKey = self::CACHE_PREFIX . "event.{$eventId}.{$flag}";

        return Cache::remember($cacheKey, self::EVENT_CACHE_TTL, function () use ($flag, $eventId) {
            try {
                $row = DB::table('feature_flag_event_overrides')
                    ->where('event_id', $eventId)->where('flag', $flag)->first();
                return $row ? (bool)$row->enabled : null;
            } catch (\Throwable) {
                return null;
            }
        });
    }
}
