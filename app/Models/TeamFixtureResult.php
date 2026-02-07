<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamFixtureResult extends Model
{
  use HasFactory;

  protected $fillable = [
    'team_fixture_id',
    'match_winner_id',
    'match_loser_id',
    'team1_score',
    'team2_score',
    'set_nr',
  ];

  public function fixture()
  {
    return $this->belongsTo(TeamFixture::class, 'team_fixture_id');
  }

  public function winnerTeam()
  {
    return $this->belongsTo(Team::class, 'match_winner_id');
  }

  public function loserTeam()
  {
    return $this->belongsTo(Team::class, 'match_loser_id');
  }
  public function winner()
  {
    return $this->belongsTo(Player::class, 'match_winner_id');
  }

  public function loser()
  {
    return $this->belongsTo(Player::class, 'match_loser_id');
  }

  public function winTeamNameShort()
  {
    $fixture = $this->fixture;
    if (!$fixture) {
      return null;
    }

    // --- Determine winning side ---
    $winnerSide = null;

    // Prefer match_winner_id if set
    if ($this->match_winner_id) {
      if ($this->match_winner_id == $fixture->team1->pluck('id')->first()) {
        $winnerSide = 'region1';
      } elseif ($this->match_winner_id == $fixture->team2->pluck('id')->first()) {
        $winnerSide = 'region2';
      }
    } elseif (is_numeric($this->team1_score) && is_numeric($this->team2_score)) {
      // Fallback: compare scores
      $winnerSide = $this->team1_score > $this->team2_score ? 'region1'
        : ($this->team2_score > $this->team1_score ? 'region2' : null);
    }

    if (!$winnerSide) {
      return 'Draw';
    }

    // --- Fetch the region record ---
    $regionId = $fixture->{$winnerSide};
    $region = \App\Models\TeamRegion::find($regionId);

    if ($region) {
      return $region->short_name ?? $region->region_name ?? 'Unknown';
    }

    return 'Unknown';
  }



}
