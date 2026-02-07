<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistrationOrderItems extends Model
{
    use HasFactory;

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function category_event()
    {
       
        return $this->belongsTo(CategoryEvent::class,'category_event_id','id');
  
    }
    public function user()
    {
       
        return $this->belongsTo(User::class,'user_id','id');
  
    }

    public function player()
    {
       
        return $this->belongsTo(Player::class,'player_id','id');
  
    }

    

}
