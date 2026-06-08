<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'fixture_id',
        'draw_id',
        'venue_id',
        'time',
    ];

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function fixture()
    {
        return $this->belongsTo(Fixture::class, 'fixture_id');
    }

    public function draw()
    {
        return $this->belongsTo(Draw::class, 'draw_id');
    }
}
