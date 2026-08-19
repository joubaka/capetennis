<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryResult extends Model
{
  protected $fillable = [
    'event_id',
    'category_id',
    'registration_id',
    'position',
  ];

  public function registration()
  {
    return $this->belongsTo(Registration::class, 'registration_id');
  }

  public function event()
  {
    return $this->belongsTo(Event::class, 'event_id');
  }

  public function category()
  {
    return $this->belongsTo(Category::class, 'category_id');
  }
}
