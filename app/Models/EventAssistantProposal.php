<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventAssistantProposal extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['payload' => 'array', 'result' => 'array', 'expires_at' => 'datetime', 'applied_at' => 'datetime'];
}
