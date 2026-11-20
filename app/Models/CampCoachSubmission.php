<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampCoachSubmission extends Model
{
    protected $guarded = [];
    protected $casts = ['date_of_birth' => 'date', 'reviewed_at' => 'datetime'];
    public function coachLink() { return $this->belongsTo(CampCoachLink::class); }
}
