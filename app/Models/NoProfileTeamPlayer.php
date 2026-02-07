<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NoProfileTeamPlayer extends Model
{
    use HasFactory;
    protected $fillable = ['team_id', 'name','surname','pay_status','rank'];

  public function profile()
  {
    return $this->belongsTo(\App\Models\Player::class, 'player_profile');
  }

  public function team()
  {
    return $this->belongsTo(Team::class, 'team_id', 'id');
  }

}
