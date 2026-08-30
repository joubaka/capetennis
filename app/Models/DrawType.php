<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrawType extends Model
{
    use HasFactory;
    public function draws(){
        return $this->hasMany(Draw::class, 'drawType_id', 'id');
    }

    public function scorings(){
        return $this->belongsTo(Scoring::class,'scoring_id','id');
    }

  public function getNameAttribute()
  {
    return $this->drawTypeName;
  }

}
