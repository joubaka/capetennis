<?php

// app/Models/ClothingItemType.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClothingItemType extends Model
{
  protected $table = 'clothing_item_types';
  protected $fillable = ['item_type_name', 'price', 'region_id', 'ordering'];

  protected $casts = ['price' => 'decimal:2'];

  public function region()
  {
    return $this->belongsTo(TeamRegion::class, 'region_id');
  }

  public function sizes()
  {
    return $this->hasMany(ClothingSize::class, 'item_type', 'id')
      ->orderBy('ordering')->orderBy('size');
  }

  public function orderItems()
  {
    return $this->hasMany(ClothingOrderItem::class, 'clothing_order_item_id');
  }
}
