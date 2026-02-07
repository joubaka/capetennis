<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    use HasFactory;
    public function fixtures(){
        return $this->belongsToMany(Fixture::class,'fixture_results','result_id','fixture_id');
     }

     public function w_registration()
     {
         return $this->belongsTo(Registration::class,'winner_registration','id');
     }
     public function l_registration()
     {
         return $this->belongsTo(Registration::class,'loser_registration');
     }
}
