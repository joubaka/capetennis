<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamTie extends Model
{
    use HasFactory;

    protected $table = 'team_ties';

    protected $fillable = [
        'draw_id',
        'round_nr',
        'tie_nr',
        'home_team_id',
        'away_team_id',
        'status',
        'winner_team_id',
        'published_at',
        'format_snapshot',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'format_snapshot' => 'array',
        'round_nr'     => 'integer',
        'tie_nr'       => 'integer',
    ];

    /** Allowed status values. */
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_COMPLETED = 'completed';

    // ─── Relations ──────────────────────────────────────────────────────────

    public function draw()
    {
        return $this->belongsTo(Draw::class, 'draw_id');
    }

    public function homeTeam()
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam()
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function winnerTeam()
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    public function getHomeSideNameAttribute(): ?string
    {
        if (!$this->home_team_id && $this->draw?->isPlacementDraft()) return $this->draw->team_draw_selection['competition']['group'].' round-robin '.($this->tie_nr === 1 ? '1st' : '3rd');
        return app(\App\Services\TeamDrawSideResolver::class)->label($this->draw, $this->homeTeam);
    }

    public function getAwaySideNameAttribute(): ?string
    {
        if (!$this->away_team_id && $this->draw?->isPlacementDraft()) return $this->draw->team_draw_selection['competition']['group'].' round-robin '.($this->tie_nr === 1 ? '2nd' : '4th');
        return app(\App\Services\TeamDrawSideResolver::class)->label($this->draw, $this->awayTeam);
    }

    public function rubbers()
    {
        return $this->hasMany(TeamFixture::class, 'team_tie_id')->orderBy('rubber_sequence');
    }

    // ─── State helpers ───────────────────────────────────────────────────────

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isLocked(): bool
    {
        return $this->isPublished() || $this->isCompleted();
    }

    // ─── Scopes ─────────────────────────────────────────────────────────────

    public function scopeForDraw($query, int $drawId)
    {
        return $query->where('draw_id', $drawId);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeLocked($query)
    {
        return $query->whereIn('status', [self::STATUS_PUBLISHED, self::STATUS_COMPLETED]);
    }
}
