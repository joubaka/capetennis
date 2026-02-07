<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamFixturePlayer extends Model
{
    use HasFactory;
    protected $fillable = [
      'team1_id',
      'team2_id',
      'team_fixture_id',
  ];
    public function team1()
    {
        return $this->belongsTo(Player::class,'team1_id');
    }
    public function team2()
    {
        return $this->belongsTo(Player::class,'team2_id');
    }

    public function fixture()
    {
        return $this->hasOne(TeamFixture::class,'id','team_fixture_id');
    }

    public function draw(){
        return $this->hasOneThrough(Draw::class,TeamFixture::class,'id','id');
    }
}
