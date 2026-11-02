<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapesterSupportRequest extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['lease_token_hash'];
    protected $casts = ['lease_expires_at' => 'datetime', 'acknowledged_at' => 'datetime'];
}
