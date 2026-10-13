<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventSelectionReopening extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected function casts(): array { return ['reopened_at' => 'datetime']; }
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Selection reopening history is immutable.'));
        static::deleting(fn () => throw new \LogicException('Selection reopening history is retained.'));
    }
}
