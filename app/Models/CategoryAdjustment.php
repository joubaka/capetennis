<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryAdjustment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payment_before' => 'array', 'payment_after' => 'array',
        'target_fee' => 'decimal:2', 'difference' => 'decimal:2', 'wallet_credit' => 'decimal:2',
        'payfast_amount_due' => 'decimal:2', 'wallet_reserved' => 'decimal:2',
        'wallet_debited' => 'boolean', 'payfast_paid' => 'boolean', 'pay_status' => 'boolean',
        'expires_at' => 'datetime', 'payfast_handed_off_at' => 'datetime', 'applied_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
    public function entry() { return $this->belongsTo(CategoryEventRegistration::class, 'entry_id'); }
    public function event() { return $this->belongsTo(Event::class); }
    public function targetCategory() { return $this->belongsTo(CategoryEvent::class, 'target_category_id'); }
    public function sourceCategory() { return $this->belongsTo(CategoryEvent::class, 'source_category_id'); }
}
