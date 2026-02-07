<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Withdrawels extends Model
{
    use HasFactory;
    protected $table ='withdrawels';

    public function registration(){
        return $this->belongsto(Registration::class,'registration_id','id');
     }
}
