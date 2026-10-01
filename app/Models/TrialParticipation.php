<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TrialParticipation extends Model {
    protected $guarded=['id']; protected $casts=['paid_at'=>'datetime'];
    public function order(){return $this->belongsTo(TeamPaymentOrder::class,'order_id');}
    public function slot(){return $this->belongsTo(TrialSquadSlot::class,'slot_id');}
    public function event(){return $this->belongsTo(Event::class);}
    public function player(){return $this->belongsTo(Player::class);}
    public function isPaid(): bool {return (bool)$this->order?->pay_status && $this->order?->withdrawn_at===null && !in_array($this->order?->refund_status,['completed'],true);}
}
