<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TrialPaymentProof extends Model {
    protected static function booted(): void {
        static::updating(function (self $proof): void {
            $dirty = array_keys($proof->getDirty());
            $protected = array_diff($dirty, ['status', 'reviewed_by_user_id', 'reviewed_at', 'updated_at']);
            $validReview = $proof->getOriginal('status') === 'pending'
                && in_array($proof->status, ['verified', 'rejected'], true)
                && ! is_null($proof->reviewed_by_user_id) && ! is_null($proof->reviewed_at)
                && count(array_intersect($dirty, ['status', 'reviewed_by_user_id', 'reviewed_at'])) === 3;
            if ($protected !== [] || ! $validReview) throw new \RuntimeException('Payment proof identity, evidence, and completed review are immutable.');
        });
        static::deleting(function () { throw new \RuntimeException('Payment proofs must be retained for audit.'); });
    }
    protected $guarded = ['id'];
    protected $hidden = ['path'];
    protected $casts = ['reviewed_at' => 'datetime'];
    public function order() { return $this->belongsTo(RegistrationOrder::class, 'order_id'); }
}
