<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NoProfileTeamPlayer extends Model
{
    use HasFactory;
    protected $fillable = [
      'team_id', 'name', 'surname', 'date_of_birth', 'email', 'cell_nr',
      'pay_status', 'rank', 'player_profile', 'claimed_by_user_id', 'claimed_at',
    ];

    protected $casts = [
      'date_of_birth' => 'date',
      'claimed_at' => 'datetime',
    ];

  public function profile()
  {
    return $this->belongsTo(\App\Models\Player::class, 'player_profile');
  }

  public function team()
  {
    return $this->belongsTo(Team::class, 'team_id', 'id');
  }

}
