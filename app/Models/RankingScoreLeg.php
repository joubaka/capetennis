<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RankingScoreLeg extends Model
{
  use HasFactory;

  protected $fillable = [
    'ranking_score_id',
    'player_id',
    'category_event_id',
    'event_name',
    'position',
    'points',
  ];

  public function score()
  {
    return $this->belongsTo(RankingScores::class, 'ranking_score_id');
  }
}
