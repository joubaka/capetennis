<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerAgreement extends Model
{
    protected $fillable = [
        'player_id',
        'agreement_id',
        'accepted_by_type',
        'guardian_name',
        'guardian_email',
        'guardian_phone',
        'guardian_relationship',
        'accepted_at',
        'ip_address',
        'user_agent',
        'content_snapshot',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function agreement()
    {
        return $this->belongsTo(Agreement::class);
    }
}
