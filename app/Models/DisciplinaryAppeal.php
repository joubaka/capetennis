<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DisciplinaryAppeal extends Model
{
    protected $guarded = [];
    protected $casts = ['submitted_at' => 'datetime', 'decided_at' => 'datetime'];
    public function disciplinaryCase() { return $this->belongsTo(DisciplinaryCase::class); }
}
