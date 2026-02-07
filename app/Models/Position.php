<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
  use HasFactory;

  // No need for protected $table — default "positions" works now

  protected $fillable = [
    'player_id',
    'category_event_id',
    'position',
    'round_robin_score'
  ];

  public function player()
  {
    return $this->belongsTo(Player::class, 'player_id');
  }
  // App\Models\Position.php
  public function registration()
  {
    // positions.player_id -> registrations.id
    return $this->belongsTo(\App\Models\Registration::class, 'player_id');
  }

  public function categoryEvent()
  {
    return $this->belongsTo(CategoryEvent::class, 'category_event_id', 'id');
  }

}
