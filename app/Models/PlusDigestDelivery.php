<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlusDigestDelivery extends Model
{
    protected $table = 'plus_digest_deliveries';
    protected $guarded = [];
    protected $casts = ['items' => 'array', 'digest_date' => 'date', 'queued_at' => 'datetime', 'claimed_at' => 'datetime', 'sent_at' => 'datetime'];
}
