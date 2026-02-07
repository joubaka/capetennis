<?php

namespace App\Models;

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
  ];

  /** ------------------------
   * Fixture Type Helpers
   * ---------------------- */
  public function isDoubles(): bool
  {
    return $this->fixture_type === 'doubles';
  }

  public function isSingles(): bool
  {
    return $this->fixture_type === 'singles';
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
    return $this->belongsTo(Venues::class, 'venue_id', 'id');
  }

  /** ------------------------
   * Teams (if you store full team refs)
   * ---------------------- */
  public function homeTeam()
  {
    return $this->belongsTo(Team::class, 'home_team_id');
  }

  public function awayTeam()
  {
    return $this->belongsTo(Team::class, 'away_team_id');
  }
}
