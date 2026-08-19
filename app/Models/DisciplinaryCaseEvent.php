<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use LogicException;
class DisciplinaryCaseEvent extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Case timeline entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Case timeline entries cannot be deleted.'));
    }
    public function disciplinaryCase() { return $this->belongsTo(DisciplinaryCase::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
}
