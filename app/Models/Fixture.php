<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fixture extends Model
{
  use HasFactory;

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

    // Teams / Inter-districts legacy
    'region1',
    'region2',
    'tie_nr',
    'home_rank_nr',
    'away_rank_nr',
  ];

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
    if ($this->fixtureResults->isEmpty()) {
      return null;
    }

    $last = $this->fixtureResults->sortBy('set_nr')->last();

    // Determine winner based on the LAST SET scores
    if ($last->registration1_score > $last->registration2_score) {
      return $this->registration1_id;
    }
    if ($last->registration2_score > $last->registration1_score) {
      return $this->registration2_id;
    }

    return null;
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
    return $this->belongsTo(\App\Models\Venues::class, 'venue_id', 'id');
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
