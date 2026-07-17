<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamEventFormatRubber extends Model
{
    use HasFactory;

    protected $table = 'team_event_format_rubbers';

    protected $fillable = [
        'format_id',
        'sequence',
        'rubber_code',
        'name',
        'gender_rule',
        'player_count_per_team',
        'singles_position',
        'reverse_from_position',
        'is_required',
    ];

    protected $casts = [
        'sequence'              => 'integer',
        'player_count_per_team' => 'integer',
        'singles_position'      => 'integer',
        'reverse_from_position' => 'integer',
        'is_required'           => 'boolean',
    ];

    /** Allowed rubber codes. */
    public const RUBBER_CODES = [
        'singles',
        'reverse_singles',
        'doubles',
        'mixed_doubles',
    ];

    // ─── Relations ──────────────────────────────────────────────────────────

    public function format()
    {
        return $this->belongsTo(TeamEventFormat::class, 'format_id');
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    public function isDoubles(): bool
    {
        return in_array($this->rubber_code, ['doubles', 'mixed_doubles'], true);
    }

    public function isSingles(): bool
    {
        return in_array($this->rubber_code, ['singles', 'reverse_singles'], true);
    }

    public function playerCountPerTeam(): int
    {
        return $this->player_count_per_team ?? ($this->isDoubles() ? 2 : 1);
    }
}
