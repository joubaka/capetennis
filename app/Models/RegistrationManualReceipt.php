<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RegistrationManualReceipt extends Model {
    protected static function booted(): void {
        static::updating(function () { throw new \RuntimeException('Verified receipts are immutable; record a separate reversal.'); });
        static::deleting(function () { throw new \RuntimeException('Verified receipts must be retained for audit.'); });
    }
    protected $guarded = ['id'];
    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    public function order() { return $this->belongsTo(RegistrationOrder::class, 'order_id'); }
}
