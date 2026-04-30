<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventPayout extends Model
{
    use HasFactory;

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
        'paid_at' => 'datetime',
        'amount'  => 'float',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function convenor()
    {
        return $this->belongsTo(EventConvenor::class, 'convenor_id');
    }

    public function paidByUser()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Display name of who received the payout.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->convenor && $this->convenor->user) {
            return $this->convenor->user->name;
        }

        return $this->recipient_name ?? 'Unknown';
    }
}
