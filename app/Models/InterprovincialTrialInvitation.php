<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterprovincialTrialInvitation extends Model
{
    protected $fillable = ['batch_id', 'event_id', 'category_event_id', 'nomination_id', 'player_id', 'recipient_email', 'recipient_name', 'status', 'queued_at', 'sent_at'];
    protected $casts = ['queued_at' => 'datetime', 'sent_at' => 'datetime'];

    public function batch() { return $this->belongsTo(InterprovincialTrialInvitationBatch::class, 'batch_id'); }
    public function event() { return $this->belongsTo(Event::class); }
    public function categoryEvent() { return $this->belongsTo(CategoryEvent::class); }
    public function player() { return $this->belongsTo(Player::class); }
}
