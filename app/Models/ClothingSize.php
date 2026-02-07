<?php

// app/Models/ClothingSize.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClothingSize extends Model
{
  protected $table = 'clothing_sizes';
  protected $fillable = ['size', 'item_type', 'ordering'];

  public function itemType()
  {
    return $this->belongsTo(ClothingItemType::class, 'item_type', 'id');
  }
}

