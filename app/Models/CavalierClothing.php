<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CavalierClothing extends Model
{
    protected $table = "cavalier_clothings";
    use HasFactory;
    public function shirts()
    {
        return $this->belongsTo(ClothingSize::class,'shirt_id');
    }

    public function shorts()
    {
        return $this->belongsTo(ClothingSize::class,'short_id');
    }
    public function long_sleeves()
    {
        return $this->belongsTo(ClothingSize::class,'long_sleeve_id');
    }
    public function hot_pants()
    {
        return $this->belongsTo(ClothingSize::class,'hot_pants_id');
    }
    public function caps()
    {
        return $this->belongsTo(ClothingSize::class,'cap_id');
    }
    public function peaks()
    {
        return $this->belongsTo(ClothingSize::class,'peak_id');
    }
    public function skirts()
    {
        return $this->belongsTo(ClothingSize::class,'skirt_id');
    }
    public function team_name()
    {
        return $this->belongsTo(Team::class,'team','id');
    }
}
