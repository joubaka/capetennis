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
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    protected static function booted(): void
    {
        static::updating(function (self $payout) {
            if ($payout->getRawOriginal('purpose') === 'expense_reimbursement') {
                throw \Illuminate\Validation\ValidationException::withMessages(['payout' => 'Recorded expense reimbursements are immutable.']);
            }
        });
        static::deleting(function (self $payout) {
            if ($payout->purpose === 'expense_reimbursement') {
                throw \Illuminate\Validation\ValidationException::withMessages(['payout' => 'Recorded expense reimbursements cannot be deleted.']);
            }
        });
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
