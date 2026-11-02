<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerPlusAccessOverride extends Model
{
    protected $guarded = [];

    protected $casts = ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'version' => 'integer'];
}
