<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MastersInvitationBatch extends Model
{
    protected $guarded = [];

    protected $casts = [
        'auto_replacement_enabled' => 'boolean',
        'public_list_published' => 'boolean',
        'registration_open' => 'boolean',
        'response_deadline' => 'datetime',
        'payment_deadline' => 'datetime',
        'replacement_payment_deadline' => 'datetime',
        'readiness_json' => 'array',
    ];

    public function invitations()
    {
        return $this->hasMany(MastersInvitation::class, 'batch_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function series()
    {
        return $this->belongsTo(Series::class);
    }
}
