<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeriesRanking extends Model
{
  protected $fillable = [
    'series_id',
    'category_id',
    'player_id',
    'rank_position',
    'total_points',
    'meta_json',
  ];

  protected $casts = [
    'meta_json' => 'array',
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

  public function registration()
  {
    return $this->belongsTo(
      \App\Models\Registration::class,
      'player_id', // FK
      'id'
    );
  }


}

