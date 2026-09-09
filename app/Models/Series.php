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
    'ranking_rule_preset_id',
    'leaderboard_published',
    'best_num_of_scores',
    'minimum_events_for_team_selection',
    'points_template_created', // ✅ ADD THIS
    'auto_award_rule',
    'use_third_score_tiebreak',
    'use_last_leg_position_tiebreak',
    'use_head_to_head_tiebreak',
    'ranking_review_default_hours',
  ];
  protected $casts = [
    'year' => 'integer',
    'best_of' => 'integer',
    'published' => 'boolean',
    'leaderboard_published' => 'boolean',
    'minimum_events_for_team_selection' => 'integer',
    'auto_award_rule' => 'boolean',
    'use_third_score_tiebreak' => 'boolean',
    'use_last_leg_position_tiebreak' => 'boolean',
    'use_head_to_head_tiebreak' => 'boolean',
    'ranking_review_default_hours' => 'integer',
  ];

  //used
  public function ranking_lists()
  {
    return $this->hasMany(RankingList::class, 'series_id', 'id');
  }

  public function rankings()
  {
    return $this->hasMany(SeriesRanking::class, 'series_id', 'id');
  }
  public function rankType()
  {
    return $this->belongsTo(RankType::class, 'rank_type', 'id');
  }

  public function rankingRulePreset()
  {
    return $this->belongsTo(RankingRulePreset::class, 'ranking_rule_preset_id');
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
