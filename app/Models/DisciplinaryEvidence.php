<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DisciplinaryEvidence extends Model
{
    protected $table = 'disciplinary_evidence';
    protected $guarded = [];
    public function disciplinaryCase() { return $this->belongsTo(DisciplinaryCase::class); }
    public function submitter() { return $this->belongsTo(User::class, 'submitted_by'); }
}
