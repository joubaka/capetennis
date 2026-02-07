<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RankingListCategoryEvent extends Model
{
  use HasFactory;

  protected $table = 'ranking_list_category_events';

  protected $fillable = [
    'ranking_list_id',
    'category_event_id',
    'sort_order',
  ];

  public function eventCategory()
  {
    return $this->belongsTo(CategoryEvent::class, 'category_event_id');
  }
}

