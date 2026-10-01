<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TrialPaymentProof extends Model {
    protected $guarded = ['id'];
    protected $hidden = ['path'];
    protected $casts = ['reviewed_at' => 'datetime'];
    public function order() { return $this->belongsTo(RegistrationOrder::class, 'order_id'); }
}
