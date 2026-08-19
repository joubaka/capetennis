<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerDuplicateDecision extends Model
{
    protected $guarded = [];

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
