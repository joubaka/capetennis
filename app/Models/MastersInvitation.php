<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MastersInvitation extends Model
{
    public const RESERVE = 'reserve';
    public const INVITED = 'invited';
    public const ACCEPTED_PENDING_PAYMENT = 'accepted_pending_payment';
    public const PAID_CONFIRMED = 'paid_confirmed';
    public const DECLINED = 'declined';
    public const EXPIRED = 'expired';
    public const WITHDRAWN = 'withdrawn';
    public const SUPERSEDED = 'superseded';
    public const NO_REPLACEMENT = 'no_replacement_available';

    protected $guarded = [];

    protected $casts = [
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
        'paid_at' => 'datetime',
        'declined_at' => 'datetime',
        'decline_confirmation_sent_at' => 'datetime',
        'decline_confirmed_at' => 'datetime',
        'expired_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'replacement_sent_at' => 'datetime',
        'snapshot_json' => 'array',
    ];

    public function batch()
    {
        return $this->belongsTo(MastersInvitationBatch::class, 'batch_id');
    }

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function categoryEvent()
    {
        return $this->belongsTo(CategoryEvent::class);
    }

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function order()
    {
        return $this->belongsTo(RegistrationOrder::class, 'order_id');
    }

    public function promotedFrom()
    {
        return $this->belongsTo(self::class, 'promoted_from_id');
    }
}
