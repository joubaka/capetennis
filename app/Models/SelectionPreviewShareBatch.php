<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SelectionPreviewShareBatch extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['recipient_ids' => 'array']; }
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Preview share batches are immutable.'));
        static::deleting(fn () => throw new \LogicException('Preview share batches retain their audit history.'));
    }
}
