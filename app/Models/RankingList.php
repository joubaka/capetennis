<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RankingList extends Model
{
    use HasFactory;
  
    public function ranking_scores()
    {
       return $this->hasMany(RankingScores::class,'ranking_list_id','id')->orderByDesc('total_points');
    }

  public function series()
  {
    return $this->belongsTo(Series::class);
  }


  public function rank_cats()
  {
  return $this->hasMany(RankingListCategoryEvent::class,'ranking_list_id','id');
  }


  public function category()
  {
      return $this->belongsTo(Category::class,'category_id','id');
  }
}
