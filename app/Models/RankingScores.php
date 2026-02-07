<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RankingScores extends Model
{
  use HasFactory;

  protected $table = 'ranking_scores';
  public $timestamps = true;

  protected $fillable = [
    'ranking_list_id',
    'player_id',
    'num_events',
    'total_points',
  ];

  protected $casts = [
    'ranking_list_id' => 'integer',
    'player_id' => 'integer',
    'num_events' => 'integer',
    'total_points' => 'integer',
   
  ];

  public function player()
  {
    return $this->belongsTo(Player::class, 'player_id');
  }

  public function rankingList()
  {
    return $this->belongsTo(RankingList::class, 'ranking_list_id');
  }
  public function legs()
  {
    return $this->hasMany(RankingScoreLeg::class, 'ranking_score_id');
  }
}
