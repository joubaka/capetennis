<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamSubstitution extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Substitution history is immutable.'));
        static::deleting(fn () => throw new \LogicException('Substitution history is immutable.'));
    }
}
