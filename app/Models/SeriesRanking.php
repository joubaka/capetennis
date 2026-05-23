<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeriesRanking extends Model
{
  protected $fillable = [
    'series_id',
    'ranking_list_id',
    'category_id',
    'player_id',
    'rank_position',
    'total_points',
    'meta_json',
    'status',
    'run_id',
    'reviewed_by',
    'reviewed_at',
    'published_by',
    'published_at',
  ];

  protected $casts = [
    'meta_json'    => 'array',
    'reviewed_at'  => 'datetime',
    'published_at' => 'datetime',
  ];

  public function player()
  {
    return $this->belongsTo(Player::class);
  }

  public function category()
  {
    return $this->belongsTo(Category::class);
  }

  public function series()
  {
    return $this->belongsTo(Series::class);
  }

  public function rankingList()
  {
    return $this->belongsTo(RankingList::class);
  }

  public function registration()
  {
    return $this->belongsTo(
      \App\Models\Registration::class,
      'player_id', // FK
      'id'
    );
  }


}

