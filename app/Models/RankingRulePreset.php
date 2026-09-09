<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class RankingRulePreset extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'rules',
        'is_system',
        'created_by',
    ];

    protected $casts = [
        'rules' => 'array',
        'is_system' => 'boolean',
        'created_by' => 'integer',
    ];
}
