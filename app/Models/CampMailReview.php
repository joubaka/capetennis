<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampMailReview extends Model
{
    protected $guarded = [];
    protected $casts = ['snapshot' => 'array', 'expires_at' => 'datetime', 'queued_at' => 'datetime'];
}
