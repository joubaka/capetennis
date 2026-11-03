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
        'purpose',
        'funding_source',
        'idempotency_key',
        'recipient_user_id',
        'recorded_by_name',
        'settlement_details',
        'reversal_of_id',
        'reversal_reason',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
        'settlement_details' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    protected static function booted(): void
    {
        static::updating(function (self $payout) {
            throw \Illuminate\Validation\ValidationException::withMessages(['payout' => 'Recorded payouts are immutable. Append an accounting correction for an incorrect record.']);
        });
        static::deleting(function (self $payout) {
            throw \Illuminate\Validation\ValidationException::withMessages(['payout' => 'Recorded payouts cannot be deleted.']);
        });
    }

    public function reversal()
    {
        return $this->hasOne(self::class, 'reversal_of_id');
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
