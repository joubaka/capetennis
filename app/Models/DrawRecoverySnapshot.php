<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrawRecoverySnapshot extends Model
{
    protected $fillable = [
        'draw_id', 'recovery_case_id', 'created_by', 'kind', 'checksum', 'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function draw()
    {
        return $this->belongsTo(Draw::class);
    }

    public function recoveryCase()
    {
        return $this->belongsTo(DrawRecoveryCase::class);
    }
}
