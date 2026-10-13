<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventSelectionFinalization extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected function casts(): array { return ['draft_versions' => 'array', 'groups' => 'array', 'finalized_at' => 'datetime']; }
    public function actor() { return $this->belongsTo(User::class, 'finalized_by'); }
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Finalized rankings are immutable.'));
        static::deleting(fn () => throw new \LogicException('Finalized ranking history is retained.'));
    }
}
