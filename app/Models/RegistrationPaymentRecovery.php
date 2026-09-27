<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationPaymentRecovery extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'before_state' => 'array',
        'mail_snapshot' => 'array',
        'prepared_at' => 'datetime',
        'mail_queued_at' => 'datetime',
        'mail_authorized_at' => 'datetime',
        'mail_sending_at' => 'datetime',
        'mail_sent_at' => 'datetime',
        'paid_at' => 'datetime',
        'link_expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $recovery): void {
            $sealed = (string) $recovery->getOriginal('before_state_checksum');
            if ($sealed !== '' && $sealed !== str_repeat('0', 64) && $recovery->isDirty([
                'registration_order_id', 'user_id', 'amount_due', 'before_state', 'before_state_checksum',
                'preview_state_hash', 'mail_snapshot', 'mail_snapshot_checksum', 'link_expires_at',
                'prepared_by', 'prepared_at',
            ])) {
                throw new \LogicException('A sealed registration payment recovery snapshot is immutable.');
            }
            if ($recovery->getOriginal('mail_authorized_at') !== null && $recovery->isDirty(['mail_queued_by', 'mail_confirmation_hash', 'mail_authorized_at'])) {
                throw new \LogicException('A recovery mail authorization is immutable.');
            }
            if ($recovery->getOriginal('mail_attempt_token') !== null && $recovery->isDirty(['mail_attempt_token', 'mail_sending_at'])) {
                throw new \LogicException('A recovery mail attempt claim is immutable.');
            }
        });
    }

    public function order()
    {
        return $this->belongsTo(RegistrationOrder::class, 'registration_order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
