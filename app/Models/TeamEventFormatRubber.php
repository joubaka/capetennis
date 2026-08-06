<?php

namespace App\Models;

use App\Domain\TeamDraw\RubberType;
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
    public const RUBBER_CODES = RubberType::ALL;

    // ─── Relations ──────────────────────────────────────────────────────────

    public function format()
    {
        return $this->belongsTo(TeamEventFormat::class, 'format_id');
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    public function isDoubles(): bool
    {
        return in_array($this->rubber_code, [
            RubberType::DOUBLES,
            RubberType::REVERSE_DOUBLES,
            RubberType::MIXED_DOUBLES,
            RubberType::REVERSE_MIXED_DOUBLES,
        ], true);
    }

    public function isSingles(): bool
    {
        return in_array($this->rubber_code, [RubberType::SINGLES, RubberType::REVERSE_SINGLES], true);
    }

    public function playerCountPerTeam(): int
    {
        return $this->player_count_per_team ?? RubberType::expectedPlayerCountPerTeam((string) $this->rubber_code);
    }
}
