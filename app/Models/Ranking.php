<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ranking extends Model
{
    use HasFactory;
    public function players()
    {
        return $this->belongsTo(Player::class,'player_id','id');
  
    }

    public function eventCategories()
    {
        return $this->belongsTo(Category_event::class,'category_event_id','id');
  
    }

    public function points1(){
        return $this->belongsTo(Point::class,'score_id_1','id');
     }

     public function points2(){
        return $this->belongsTo(Point::class,'score_id_2','id');
     }

     public function points3(){
        return $this->belongsTo(Point::class,'score_id_3','id');
     }

     
}
