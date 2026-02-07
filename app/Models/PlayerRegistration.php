<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerRegistration extends Model
{
  use HasFactory;

  // ✅ Allow mass assignment for importTeamCategoryEvents()
  protected $fillable = [
    'registration_id',
    'player_id',
  ];

  // ✅ Relationship: this entry belongs to a Player
  public function player()
  {
    return $this->belongsTo(Player::class, 'player_id');
  }

  // ✅ Relationship: this entry belongs to a Registration
  public function registration()
  {
    return $this->belongsTo(Registration::class, 'registration_id');
  }
}
