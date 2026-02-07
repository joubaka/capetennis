<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderOfPlay extends Model
{
  use HasFactory;

  protected $fillable = [
    'fixture_id',
    'draw_id',
    'venue_id',
    'court',
    'time',
  ];

  public function venue()
  {
    return $this->belongsTo(Venues::class);
  }

  public function fixture()
  {
    return $this->belongsTo(Fixture::class, 'fixture_id');
  }

  public function draw()
  {
    return $this->belongsTo(Draw::class, 'draw_id');
  }

  public function team1()
  {
    return $this->belongsToMany(Player::class, 'team_fixture_players', 'team_fixture_id', 'team1_id');
  }

  public function team2()
  {
    return $this->belongsToMany(Player::class, 'team_fixture_players', 'team_fixture_id', 'team2_id');
  }

  public function teamResults()
  {
    return $this->hasMany(TeamFixtureResult::class);
  }
}
