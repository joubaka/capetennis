<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Leaderboard extends Model
{
    use HasFactory;
    public function player()
    {
        return $this->belongsTo(Player::class);
    }
    
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function series()
    {
        return $this->belongsTo(Series::class);
    }
    public function scopeTest($query,$category_id,$series_id)
    {
       return $query
       ->where('category_id',$category_id)
       ->where('series_id',$series_id)
       ->orderBy('total_points','desc')
       ->get();
    }
    
}
