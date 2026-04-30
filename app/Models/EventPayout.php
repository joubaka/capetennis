<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPayout extends Model
{
    protected $table = 'event_payouts';

    protected $fillable = [
        'event_id',
        'amount',
        'recipient',
        'method',
        'description',
        'payout_date',
    ];

    protected $casts = [
        'amount' => 'float',
        'payout_date' => 'date',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
