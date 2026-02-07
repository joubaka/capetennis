<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team_order_of_play extends Model
{
    use HasFactory;
    protected $with = ['venue'];
    public function venue()
    {
       return $this->belongsTo(Venues::class,'venue_id','id');
    }
}
