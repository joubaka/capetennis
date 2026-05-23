<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores mismatches detected by EngineRouter during hybrid mode.
 *
 * @property int         $id
 * @property string      $operation
 * @property int|null    $draw_id
 * @property string      $mismatch_type
 * @property string      $engine_mode
 * @property array|null  $legacy_result
 * @property array|null  $canonical_result
 * @property bool        $was_fallback
 */
class EngineComparisonLog extends Model
{
    protected $table = 'engine_comparison_logs';

    protected $fillable = [
        'operation',
        'draw_id',
        'mismatch_type',
        'engine_mode',
        'legacy_result',
        'canonical_result',
        'was_fallback',
    ];

    protected $casts = [
        'legacy_result'    => 'array',
        'canonical_result' => 'array',
        'was_fallback'     => 'boolean',
    ];
}
