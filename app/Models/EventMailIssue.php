<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventMailIssue extends Model
{
    protected $guarded = [];
    protected $casts = ['read_at' => 'datetime'];
}
