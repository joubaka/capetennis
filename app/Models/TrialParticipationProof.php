<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TrialParticipationProof extends Model {
    protected $guarded=['id']; protected $hidden=['path']; protected $casts=['reviewed_at'=>'datetime'];
    public function participation(){return $this->belongsTo(TrialParticipation::class,'participation_id');}
    public function order(){return $this->belongsTo(TeamPaymentOrder::class,'order_id');}
}
