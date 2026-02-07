<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Series_Ranking extends Model
{
    use HasFactory;
    protected $table = 'series_rankings';

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class,'category_id','id');
    }

}
