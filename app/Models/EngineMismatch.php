<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EngineMismatch extends Model
{
    public $timestamps = false;

    /**
     * Severity levels, highest first for ordering.
     */
    const SEVERITY_HIGH   = 'high';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_LOW    = 'low';

    protected $fillable = [
        'draw_id',
        'operation_type',
        'mismatch_type',
        'legacy_output',
        'canonical_output',
        'severity',
        'resolved',
        'created_at',
    ];

    protected $casts = [
        'legacy_output'    => 'array',
        'canonical_output' => 'array',
        'resolved'         => 'boolean',
        'created_at'       => 'datetime',
    ];

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeUnresolved($query)
    {
        return $query->where('resolved', false);
    }

    public function scopeHighSeverity($query)
    {
        return $query->where('severity', self::SEVERITY_HIGH);
    }

    public function scopeForDraw($query, int $drawId)
    {
        return $query->where('draw_id', $drawId);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Map operation type + mismatch type to a severity level.
     */
    public static function resolveSeverity(string $operation, string $mismatchType): string
    {
        $high = [
            'progression_mismatch',
            'winner_not_placed_in_parent',
            'bye_mismatch',
            'playoff_mapping_mismatch',
            'rollback_inconsistency',
        ];
        $low = [
            'canonical_snapshot',
            'comparison_threw',
            'legacy_render_threw',
            'legacy_standings_threw',
        ];

        if (in_array($mismatchType, $high)) {
            return self::SEVERITY_HIGH;
        }
        if (in_array($mismatchType, $low)) {
            return self::SEVERITY_LOW;
        }
        return self::SEVERITY_MEDIUM;
    }

    /**
     * Top N mismatch types by occurrence.
     */
    public static function topTypes(int $limit = 10): array
    {
        return static::selectRaw('mismatch_type, operation_type, count(*) as total')
            ->groupBy('mismatch_type', 'operation_type')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
