<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CtrDrawPlan extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['proposal' => 'array', 'draw_ids' => 'array', 'expires_at' => 'datetime', 'applied_at' => 'datetime'];
}
