<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivateRegistrationLink extends Model
{
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime'];

    public function categoryEvent(): BelongsTo
    {
        return $this->belongsTo(CategoryEvent::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(RegistrationOrder::class);
    }
}
