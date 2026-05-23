<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EngineRun extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'draw_id',
        'engine_mode',
        'operation_type',
        'legacy_success',
        'canonical_success',
        'mismatch_detected',
        'fallback_used',
        'mismatch_count',
        'duration_ms',
        'exception',
        'created_at',
    ];

    protected $casts = [
        'legacy_success'    => 'boolean',
        'canonical_success' => 'boolean',
        'mismatch_detected' => 'boolean',
        'fallback_used'     => 'boolean',
        'created_at'        => 'datetime',
    ];

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeForDraw($query, int $drawId)
    {
        return $query->where('draw_id', $drawId);
    }

    public function scopeWithMismatches($query)
    {
        return $query->where('mismatch_detected', true);
    }

    public function scopeWithFallbacks($query)
    {
        return $query->where('fallback_used', true);
    }

    public function scopeForOperation($query, string $op)
    {
        return $query->where('operation_type', $op);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Canonical confidence score for this set of runs (0–100).
     */
    public static function confidenceScore(): array
    {
        $total     = static::count();
        $canonical = static::where('engine_mode', '!=', 'legacy')->count();

        if ($canonical === 0) {
            return [
                'total_runs'           => $total,
                'canonical_runs'       => 0,
                'parity_pct'           => null,
                'mismatch_pct'         => null,
                'fallback_pct'         => null,
                'progression_ok_pct'   => null,
                'standings_ok_pct'     => null,
                'confidence_score'     => null,
                'confidence_label'     => 'no data',
            ];
        }

        $mismatches       = static::where('engine_mode', '!=', 'legacy')->where('mismatch_detected', true)->count();
        $fallbacks        = static::where('engine_mode', '!=', 'legacy')->where('fallback_used', true)->count();
        $progressionTotal = static::where('operation_type', 'progression')->where('engine_mode', '!=', 'legacy')->count();
        $progressionFail  = static::where('operation_type', 'progression')->where('engine_mode', '!=', 'legacy')->where('canonical_success', false)->count();
        $standingsTotal   = static::where('operation_type', 'standings')->where('engine_mode', '!=', 'legacy')->count();
        $standingsFail    = static::where('operation_type', 'standings')->where('engine_mode', '!=', 'legacy')->where('canonical_success', false)->count();

        $parityPct     = $canonical > 0 ? round((($canonical - $mismatches) / $canonical) * 100, 2) : null;
        $mismatchPct   = $canonical > 0 ? round(($mismatches / $canonical) * 100, 2) : null;
        $fallbackPct   = $canonical > 0 ? round(($fallbacks  / $canonical) * 100, 2) : null;
        $progressionPct = $progressionTotal > 0 ? round((($progressionTotal - $progressionFail) / $progressionTotal) * 100, 2) : null;
        $standingsPct   = $standingsTotal   > 0 ? round((($standingsTotal   - $standingsFail)   / $standingsTotal)   * 100, 2) : null;

        // Weight: parity 40%, no-fallback 30%, progression 20%, standings 10%
        $weights = [];
        if ($parityPct    !== null) { $weights[] = $parityPct    * 0.40; }
        if ($fallbackPct  !== null) { $weights[] = (100 - $fallbackPct) * 0.30; }
        if ($progressionPct !== null) { $weights[] = $progressionPct * 0.20; }
        if ($standingsPct   !== null) { $weights[] = $standingsPct   * 0.10; }

        $score = count($weights) > 0 ? round(array_sum($weights) / (count($weights) / count($weights)), 2) : null;

        $label = match(true) {
            $score === null         => 'no data',
            $score >= 98            => 'production-ready',
            $score >= 90            => 'high confidence',
            $score >= 75            => 'moderate confidence',
            $score >= 50            => 'low confidence',
            default                 => 'unsafe',
        };

        return [
            'total_runs'           => $total,
            'canonical_runs'       => $canonical,
            'parity_pct'           => $parityPct,
            'mismatch_pct'         => $mismatchPct,
            'fallback_pct'         => $fallbackPct,
            'progression_ok_pct'   => $progressionPct,
            'standings_ok_pct'     => $standingsPct,
            'confidence_score'     => $score,
            'confidence_label'     => $label,
        ];
    }
}
