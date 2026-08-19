<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DisciplinaryCharge extends Model
{
    protected $guarded = [];
    public function disciplinaryCase() { return $this->belongsTo(DisciplinaryCase::class); }
    public function violationType() { return $this->belongsTo(ViolationType::class); }
}
