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
}
