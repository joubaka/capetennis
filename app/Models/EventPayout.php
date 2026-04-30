<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPayout extends Model
{
    protected $table = 'event_payouts';

    protected $fillable = [
        'event_id',
        'convenor_id',
        'recipient_name',
        'amount',
        'description',
        'payment_method',
        'reference',
        'paid_by',
        'paid_at',
    ];

    protected $casts = [
        'amount'  => 'float',
        'paid_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function convenor()
    {
        return $this->belongsTo(EventConvenor::class, 'convenor_id');
    }

    public function paidByUser()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
