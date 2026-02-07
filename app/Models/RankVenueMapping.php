
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RankVenueMapping extends Model
{
    protected $fillable = ['draw_id', 'rank', 'venue_id'];

    public function draw() {
        return $this->belongsTo(Draw::class);
    }

    public function venue() {
        return $this->belongsTo(Venue::class);
    }
}
