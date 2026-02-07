<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Overberg_clothing extends Model
{
    use HasFactory;
    protected $table = "overberg_clothings";
    public function shirts()
    {
        return $this->belongsTo(ClothingSize::class,'shirt_id');
    }

    public function hoodies()
    {
        return $this->belongsTo(ClothingSize::class,'hoodie_id');
    }

    public function caps()
    {
        return $this->belongsTo(ClothingSize::class,'cap_id');
    }

    public function team_name()
    {
        return $this->belongsTo(Team::class,'team','id');
    }
    public function skirts()
    {
        return $this->belongsTo(ClothingSize::class,'skirt_id','id');
    }
    public function hot_pants()
    {
        return $this->belongsTo(ClothingSize::class,'hot_pants_id','id');
    }
    public function pants()
    {
        return $this->belongsTo(ClothingSize::class,'pants_id','id');
    }

    public function peaks()
    {
        return $this->belongsTo(ClothingSize::class,'peak_id','id');
    }

    public function jackets()
    {
        return $this->belongsTo(ClothingSize::class,'jacket_id','id');
    }

}
