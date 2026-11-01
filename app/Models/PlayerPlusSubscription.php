<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerPlusSubscription extends Model
{
    protected $guarded = [];
    protected $hidden = ['provider_token', 'provider_token_hash'];
    protected $casts = [
        'provider_token' => 'encrypted', 'paid_cycles' => 'integer',
        'billing_anchor_at' => 'immutable_datetime', 'next_billing_at' => 'immutable_datetime',
        'cancellation_requested_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime',
    ];

    public function order() { return $this->belongsTo(PlayerPlusOrder::class); }
}
