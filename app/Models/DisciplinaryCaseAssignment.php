<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DisciplinaryCaseAssignment extends Model
{
    protected $guarded = [];
    protected $casts = ['conflict_declared' => 'boolean', 'accepted_at' => 'datetime', 'recused_at' => 'datetime'];
    public function disciplinaryCase() { return $this->belongsTo(DisciplinaryCase::class); }
    public function user() { return $this->belongsTo(User::class); }
}
