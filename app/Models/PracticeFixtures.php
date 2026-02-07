<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PracticeFixtures extends Model
{
    use HasFactory;

    public function player1()
    {
        return $this->belongsTo(Player::class,'registration1_id','id');
        
    }
    public function player2()
    {
        return $this->belongsTo(Player::class,'registration2_id','id');
        
    }
    public function practice()
    {
        return $this->belongsTo(Practice::class,'practice_id','id');
        
    }

    public function noProfile()
    {
        return $this->hasOne(NoProfilePlayer::class,'fixture_practice_id','id');
        
    }
    public function results()
    {
        return $this->hasMany(PracticeResults::class,'practice_fixture_id','id');
        
    }
}
