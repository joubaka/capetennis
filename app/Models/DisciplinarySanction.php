<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
class DisciplinarySanction extends Model
{
    protected $guarded = [];
    protected $casts = ['starts_at' => 'date', 'ends_at' => 'date', 'stayed' => 'boolean', 'revoked_at' => 'datetime'];
    public function disciplinaryCase() { return $this->belongsTo(DisciplinaryCase::class); }
    public function decision() { return $this->belongsTo(DisciplinaryDecision::class, 'disciplinary_decision_id'); }
    public function player() { return $this->belongsTo(Player::class); }
    public function scopeEffective(Builder $query): Builder
    {
        return $query->where('stayed', false)->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', today()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', today()));
    }
}
