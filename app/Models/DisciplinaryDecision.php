<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use LogicException;
class DisciplinaryDecision extends Model
{
    protected $guarded = [];
    protected $casts = ['panel_snapshot' => 'array', 'rule_snapshot' => 'array', 'decided_at' => 'datetime', 'served_at' => 'datetime'];
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Final disciplinary decisions are immutable.'));
        static::deleting(fn () => throw new LogicException('Final disciplinary decisions cannot be deleted.'));
    }
    public function disciplinaryCase() { return $this->belongsTo(DisciplinaryCase::class); }
    public function sanctions() { return $this->hasMany(DisciplinarySanction::class); }
}
