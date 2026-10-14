<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventSelectionRosterImport extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected function casts(): array { return ['groups' => 'array', 'imported_at' => 'datetime']; }
    public function finalization() { return $this->belongsTo(EventSelectionFinalization::class); }
    public function region() { return $this->belongsTo(TeamRegion::class); }
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Imported selection history is immutable.'));
        static::deleting(fn () => throw new \LogicException('Imported selection history is retained.'));
    }
}
