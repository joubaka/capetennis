<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HostEventApplication extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['requested_categories' => 'array', 'court_plan' => 'array', 'start_date' => 'date', 'end_date' => 'date', 'reviewed_at' => 'datetime'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function notifications()
    {
        return $this->hasMany(HostEventNotification::class, 'application_id');
    }
}
