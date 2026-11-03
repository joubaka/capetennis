<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlusPublicationDelivery extends Model
{
    protected $guarded = [];
    protected $casts = ['changes' => 'array', 'claimed_at' => 'datetime', 'sent_at' => 'datetime'];
}
