<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TrialParticipationReceipt extends Model {
    protected static function booted(): void {
        static::updating(function () { throw new \RuntimeException('Verified participation receipts are immutable; record a separate reversal.'); });
        static::deleting(function () { throw new \RuntimeException('Verified participation receipts must be retained for audit.'); });
    }
    protected $guarded=['id']; protected $casts=['paid_at'=>'datetime','amount'=>'decimal:2'];
    public function participation(){return $this->belongsTo(TrialParticipation::class,'participation_id');}
    public function order(){return $this->belongsTo(TeamPaymentOrder::class,'order_id');}
}
