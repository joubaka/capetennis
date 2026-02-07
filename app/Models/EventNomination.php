<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventNomination extends Model
{
  use HasFactory;

  protected $table = 'event_nominations';

  protected $fillable = [
    'event_id',
    'category_event_id',
    'player_id',
  ];

  // Relationships
  public function player()
  {
    return $this->belongsTo(Player::class);
  }

  public function category_event()
  {
    return $this->belongsTo(CategoryEvent::class);
  }

  public function event()
  {
    return $this->belongsTo(Event::class);
  }
}
