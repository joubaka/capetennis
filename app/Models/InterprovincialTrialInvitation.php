<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterprovincialTrialInvitation extends Model
{
    public const ACCEPTED_PENDING_PAYMENT = 'accepted_pending_payment';
    public const PAID_CONFIRMED = 'paid_confirmed';
    public const WITHDRAWN = 'withdrawn';

    protected $fillable = ['batch_id', 'event_id', 'category_event_id', 'nomination_id', 'player_id', 'registration_id', 'order_id', 'recipient_email', 'recipient_name', 'status', 'queued_at', 'sent_at', 'accepted_at', 'paid_at', 'withdrawn_at'];
    protected $casts = ['queued_at' => 'datetime', 'sent_at' => 'datetime', 'accepted_at' => 'datetime', 'paid_at' => 'datetime', 'withdrawn_at' => 'datetime'];

    public function batch() { return $this->belongsTo(InterprovincialTrialInvitationBatch::class, 'batch_id'); }
    public function event() { return $this->belongsTo(Event::class); }
    public function categoryEvent() { return $this->belongsTo(CategoryEvent::class); }
    public function player() { return $this->belongsTo(Player::class); }
    public function registration() { return $this->belongsTo(Registration::class); }
    public function order() { return $this->belongsTo(RegistrationOrder::class, 'order_id'); }
}
