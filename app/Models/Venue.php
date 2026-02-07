<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{


    public function events()
    {
        return $this->belongsToMany(
            Event::class,
            'event_venues',
            'venue_id',
            'event_id'
        );
    }


}
