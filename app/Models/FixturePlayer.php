<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixturePlayer extends Model
{
  use HasFactory;

  // Optional (Laravel will infer 'fixture_players' automatically)
  protected $table = 'fixture_players';

  protected $fillable = [
    'fixture_id',
    'team1_id',
    'team2_id',
  ];

  public function fixture()
  {
    return $this->belongsTo(Fixture::class);
  }

  public function player1()
  {
    return $this->belongsTo(Player::class, 'team1_id');
  }

  public function player2()
  {
    return $this->belongsTo(Player::class, 'team2_id');
  }
}
