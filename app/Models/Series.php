<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Models\RankType;

class Series extends Model
{
  use HasFactory;
  protected $fillable = [
    'name',
    'year',
    'rank_type',
    'leaderboard_published',
    'best_num_of_scores',
    'points_template_created', // ✅ ADD THIS
  ];
  protected $casts = [
    'year' => 'integer',
    'best_of' => 'integer',
    'published' => 'boolean',
  ];

  //used
  public function ranking_lists()
  {
    return $this->hasMany(RankingList::class, 'series_id', 'id');
  }
  public function rankType()
  {
    return $this->belongsTo(RankType::class, 'rank_type', 'id');
  }

  //not used

  public function events()
  {
    return  $this->hasMany(Event::class, 'series_id', 'id');
  }


  public function ranking_categories()
  {
    return  $this->hasMany(Series_Ranking::class, 'series_id', 'id');
  }

  public function leaderboard()
  {
    return $this->hasMany(Leaderboard::class, 'series_id', 'id')->orderByDesc('category_id')->orderByDesc('total_points');
  }

  public function points()
  {
    return $this->hasMany(Point::class, 'series_id', 'id')
      ->orderBy('position');
  }


  function categories() : HasManyThrough {
    return $this->hasManyThrough(CategoryEvent::class,Event::class,'series_id','event_id');
  }
}
