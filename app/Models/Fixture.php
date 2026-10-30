<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fixture extends Model
{
  use HasFactory;

  protected static function booted(): void
  {
    static::saved(function (self $fixture): void {
      // Clearing the outcome invalidates outstanding revisions. Notification
      // creation belongs to explicit score writers, never administrative saves.
      if ($fixture->wasChanged('winner_registration') && !$fixture->winner_registration) {
        app(\App\Services\MatchResultNotificationService::class)->invalidate($fixture);
      }
    });
  }

  protected $fillable = [
    'scheduled',
    'match_nr',

    // Round Robin participants
    'registration1_id',
    'registration2_id',
    'registration1_pivot_id',   // 👈 NEW — group isolation
    'registration2_pivot_id',   // 👈 NEW — group isolation

    'round',
    'bracket_id',
    'match_status',
    'draw_id',
    'draw_group_id',

    'hint_registration1_id',
    'hint_registration2_id',

    'stage',
    'parent_fixture_id',
    'loser_parent_fixture_id',
    'winner_registration',
    'feeder_slot',
    'loser_feeder_slot',
    'position',          // Position playoff (3 = 3rd/4th, 5 = 5th/6th, etc.)
    'playoff_type',      // Playoff type label (e.g., '3rd/4th', '5th/6th', 'cons_sf1')

    // Deferred round-robin finishing-position sources. These let a playoff
    // fixture keep its identity and schedule before its players are known.
    'registration1_source_group_id',
    'registration1_source_position',
    'registration2_source_group_id',
    'registration2_source_position',

    // Teams / Inter-districts legacy
    'region1',
    'region2',
    'tie_nr',
    'home_rank_nr',
    'away_rank_nr',
  ];

  /** Query recorded results, including walkovers with no played sets. */
  public function scopeWithRecordedResult(\Illuminate\Database\Eloquent\Builder $query): void
  {
    $query->where(fn ($result) => $result->whereHas('fixtureResults')->orWhere(fn ($walkover) => $walkover
      ->where('match_status', 3)->where('registration1_id', '>', 0)->where('registration2_id', '>', 0)
      ->whereColumn('registration1_id', '!=', 'registration2_id')
      ->where(fn ($winner) => $winner->whereColumn('winner_registration', 'registration1_id')->orWhereColumn('winner_registration', 'registration2_id'))));
  }

  /** A recorded walkover has two entrants and no played set scores. */
  public function isWalkover(): bool
  {
    return (int) $this->match_status === 3
      && (int) $this->registration1_id > 0 && (int) $this->registration2_id > 0
      && (int) $this->registration1_id !== (int) $this->registration2_id
      && in_array((int) $this->winner_registration, [(int) $this->registration1_id, (int) $this->registration2_id], true)
      && ($this->relationLoaded('fixtureResults') ? $this->fixtureResults->isEmpty() : !$this->fixtureResults()->exists());
  }

  // ============================================================
  // DRAW RELATIONSHIPS
  // ============================================================

  public function draw()
  {
    return $this->belongsTo(Draw::class, 'draw_id', 'id');
  }

  public function drawGroup()
  {
    return $this->belongsTo(DrawGroup::class, 'draw_group_id', 'id');
  }

  // ============================================================
  // ROUND ROBIN REGISTRATIONS (correct model)
  // ============================================================

  public function registration1()
  {
    return $this->belongsTo(
      \App\Models\Registration::class,
      'registration1_id'
    );
  }

  public function registration2()
  {
    return $this->belongsTo(
      \App\Models\Registration::class,
      'registration2_id'
    );
  }

  // ============================================================
  // GROUP ISOLATION (pivots)
  // Lets us read seed, group slot, etc.
  // ============================================================

  public function groupRegistration1()
  {
    return $this->belongsTo(
      \App\Models\DrawGroupRegistration::class,
      'registration1_pivot_id'
    );
  }

  public function groupRegistration2()
  {
    return $this->belongsTo(
      \App\Models\DrawGroupRegistration::class,
      'registration2_pivot_id'
    );
  }

  // ============================================================
  // RESULTS
  // ============================================================

  public function fixtureResults()
  {
    return $this->hasMany(
      FixtureResult::class,
      'fixture_id',
      'id'
    )->orderBy('set_nr');
  }

  public function results()
  {
    return $this->belongsToMany(
      Result::class,
      'fixture_results',
      'fixture_id',
      'result_id'
    )->orderBy('set_nr');
  }
  public function getScoreAttribute()
  {
    if ($this->isWalkover()) return 'W/O';
    if ($this->fixtureResults->isEmpty()) {
      return null;
    }

    return $this->fixtureResults
      ->sortBy('set_nr')
      ->map(fn($s) => $s->registration1_score . '-' . $s->registration2_score)
      ->implode(' ');
  }

  public function getWinnerIdAttribute()
  {
    if ($this->fixtureResults->isEmpty() && !$this->isWalkover()) {
      return null;
    }

    return app(\App\Services\IndividualMatchOutcomeService::class)->winner($this);
  }

  public function getLoserIdAttribute()
  {
    if (!$this->winner_id)
      return null;

    return $this->winner_id == $this->registration1_id
      ? $this->registration2_id
      : $this->registration1_id;
  }


  public function results_desc()
  {
    return $this->belongsToMany(
      Result::class,
      'fixture_results',
      'fixture_id',
      'result_id'
    )->orderBy('set_nr', 'desc');
  }

  // ============================================================
  // SCHEDULES / OOP
  // ============================================================

  public function schedule()
  {
    return $this->hasOne(Schedule::class, 'fixture_id', 'id');
  }

  public function oop()
  {
    return $this->hasOne(OrderOfPlay::class, 'fixture_id', 'id');
  }

  // ============================================================
  // TEAM FIXTURE LEGACY (Inter-districts)
  // Does NOT affect Round Robin
  // ============================================================

  public function region1Name()
  {
    return $this->belongsTo(TeamRegion::class, 'region1', 'id');
  }

  public function region2Name()
  {
    return $this->belongsTo(TeamRegion::class, 'region2', 'id');
  }

  public function homeTeam()
  {
    return $this->belongsTo(\App\Models\Team::class, 'home_team_id');
  }

  public function awayTeam()
  {
    return $this->belongsTo(\App\Models\Team::class, 'away_team_id');
  }

  public function venue()
  {
    return $this->belongsTo(\App\Models\Venue::class, 'venue_id', 'id');
  }

  public function orderOfPlay()
  {
    return $this->hasOne(OrderOfPlay::class, 'fixture_id', 'id');
  }
  public function bracket()
{
    return $this->belongsTo(Bracket::class, 'bracket_id', 'id');
}
  public function registrations1()
  {
    return $this->belongsTo(\App\Models\Registration::class, 'registration1_id');
  }
  public function registrations2()
  {
    return $this->belongsTo(\App\Models\Registration::class, 'registration2_id');
  }

  public function resolveName($registration_id)
  {
    if ($registration_id == 0 || $registration_id === null) {
      return "Bye";
    }

    $reg = \App\Models\Registration::with('players')->find($registration_id);

    if (!$reg || !$reg->players || $reg->players->isEmpty()) {
      return "Unknown";
    }

    return $reg->players->first()->getFullNameAttribute();
  }

}
