<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TrialParticipationReceipt extends Model {
    protected $guarded=['id']; protected $casts=['paid_at'=>'datetime','amount'=>'decimal:2'];
    public function participation(){return $this->belongsTo(TrialParticipation::class,'participation_id');}
    public function order(){return $this->belongsTo(TeamPaymentOrder::class,'order_id');}
}
