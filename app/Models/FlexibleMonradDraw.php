<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlexibleMonradDraw extends Model
{
    protected $fillable = ['draw_id', 'revision', 'draft', 'graph', 'fixture_map'];

    protected $casts = [
        'draft' => 'array', 'graph' => 'array', 'fixture_map' => 'array', 'revision' => 'integer',
    ];
}
