<?php

// app/Models/ClothingItemType.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClothingItemType extends Model
{
  protected $table = 'clothing_item_types';
  protected $fillable = ['item_type_name', 'price', 'region_id', 'ordering'];

  public function region()
  {
    return $this->belongsTo(TeamRegion::class, 'region_id');
  }

  public function sizes()
  {
    return $this->hasMany(ClothingSize::class, 'item_type', 'id')
      ->orderBy('ordering')->orderBy('size');
  }
}

