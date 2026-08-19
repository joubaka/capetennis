<?php

namespace App\Models;

use App\Domain\TeamDraw\RubberType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TeamFixture extends Model
{
  use HasFactory;
  protected $casts = [
    'scheduled' => 'boolean',
    'scheduled_at' => 'datetime',
  ];

  protected $with = ['teamResults', 'fixturePlayers', 'draw'];

  protected $fillable = [
    'fixture_type',
    'draw_id',
    'numSets',
    'match_nr',
    'round_nr',
    'rank_nr',
    'region1',
    'tie_nr',
    'region2',
    'home_rank_nr',
    'away_rank_nr',
    'age',
    'scheduled',
    'scheduled_at',
    'venue_id',
    'court_label',
    'duration_min',
    // v2 rubber fields
    'team_tie_id',
    'rubber_sequence',
    'rubber_code',
    'rubber_name',
    'gender_rule',
    'player_count_per_team',
  ];

  /** ------------------------
   * Fixture Type Helpers
   * ---------------------- */
  public function isDoubles(): bool
  {
    // Prefer rubber_code (v2 team draws) over legacy fixture_type
    if ($this->rubber_code !== null) {
      return in_array($this->rubber_code, [RubberType::DOUBLES, RubberType::MIXED_DOUBLES], true);
    }

    // Fall back to legacy integer fixture_type (1=singles, 2=doubles, 3=mixed_doubles, 4=reverse_singles)
    return (int) $this->fixture_type === 2 || (int) $this->fixture_type === 3;
  }

  public function isSingles(): bool
  {
    // Prefer rubber_code (v2 team draws) over legacy fixture_type
    if ($this->rubber_code !== null) {
      return in_array($this->rubber_code, [RubberType::SINGLES, RubberType::REVERSE_SINGLES], true);
    }

    // Fall back to legacy integer fixture_type (1=singles, 2=doubles, 3=mixed_doubles, 4=reverse_singles)
    return (int) $this->fixture_type === 1 || (int) $this->fixture_type === 4;
  }

  /** ------------------------
   * Core Relations
   * ---------------------- */
  public function draw()
  {
    return $this->belongsTo(Draw::class, 'draw_id', 'id');
  }

  public function event()
  {
    return $this->draw?->event(); // simpler than hasOneThrough
  }

  public function region1Name()
  {
    return $this->belongsTo(TeamRegion::class, 'region1', 'id');
  }

  public function region2Name()
  {
    return $this->belongsTo(TeamRegion::class, 'region2', 'id');
  }

  /** ------------------------
   * Players
   * ---------------------- */
  public function fixturePlayers()
  {
    return $this->hasMany(TeamFixturePlayer::class, 'team_fixture_id', 'id');
  }

  public function team1()
  {
    return $this->belongsToMany(Player::class, 'team_fixture_players', 'team_fixture_id', 'team1_id');
  }

  public function team2()
  {
    return $this->belongsToMany(Player::class, 'team_fixture_players', 'team_fixture_id', 'team2_id');
  }
  public function getRegionShort($side)
  {
    $regionId = $this->{$side};
    return $regionId ? \App\Models\TeamRegion::find($regionId)?->short_name : null;
  }

  /** ------------------------
   * Results / Scheduling
   * ---------------------- */
  public function teamResults()
  {
    return $this->hasMany(TeamFixtureResult::class, 'team_fixture_id', 'id');
  }

  public function fixtureResults()
  {
    return $this->hasMany(TeamFixtureResult::class, 'team_fixture_id', 'id')->orderBy('set_nr');
  }
 

  public function orderOfPlay()
  {
    return $this->hasOne(OrderOfPlay::class, 'fixture_id', 'id');
  }

  public function venue()
  {
    return $this->belongsTo(Venue::class, 'venue_id', 'id');
  }

  /** ------------------------
   * Teams (if you store full team refs)
   * ---------------------- */
  public function homeTeam()
  {
    return $this->hasOneThrough(
      Team::class,
      TeamTie::class,
      'id',
      'id',
      'team_tie_id',
      'home_team_id'
    );
  }

  public function awayTeam()
  {
    return $this->hasOneThrough(
      Team::class,
      TeamTie::class,
      'id',
      'id',
      'team_tie_id',
      'away_team_id'
    );
  }

  public function teamTie()
  {
    return $this->belongsTo(\App\Models\TeamTie::class, 'team_tie_id');
  }
}
