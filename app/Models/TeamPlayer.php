<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamPlayer extends Model
{
  use HasFactory;

  protected $fillable = [
    'team_id',
    'player_id',
    'rank',
    'pay_status',
  ];

  public function team()
  {
    return $this->belongsTo(Team::class, 'team_id', 'id');
  }

  public function player()
  {
    return $this->belongsTo(Player::class, 'player_id', 'id')
      ->where('players.id', '>', 0);
  }


  public function teamResultsTeam1()
  {
    return $this->hasMany(TeamFixturePlayer::class, 'team1_id', 'player_id');
  }

  public function teamResultsTeam2()
  {
    return $this->hasMany(TeamFixturePlayer::class, 'team2_id', 'player_id');
  }

  /** 🔥 Always order by rank by default */
  protected static function booted()
  {
    static::addGlobalScope('rank', function ($query) {
      $query->orderBy('rank');
    });
  }

  public function noProfile()
  {
    return $this->hasOne(NoProfileTeamPlayer::class, 'rank', 'rank')
      ->where('team_id', $this->team_id);
  }


}
