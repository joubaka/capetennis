<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisciplinaryCase extends Model
{
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_TRIAGE = 'triage';
    public const STATUS_AWAITING_RESPONSE = 'awaiting_response';
    public const STATUS_PANEL_REVIEW = 'panel_review';
    public const STATUS_DECIDED = 'decided';
    public const STATUS_APPEALED = 'appealed';
    public const STATUS_FINAL = 'final';
    public const STATUS_DISMISSED = 'dismissed';

    protected $guarded = [];

    protected $casts = [
        'incident_at' => 'datetime',
        'response_due_at' => 'datetime',
        'responded_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function event() { return $this->belongsTo(Event::class); }
    public function categoryEvent() { return $this->belongsTo(CategoryEvent::class); }
    public function fixture() { return $this->belongsTo(Fixture::class); }
    public function player() { return $this->belongsTo(Player::class); }
    public function reporter() { return $this->belongsTo(User::class, 'reported_by'); }
    public function triager() { return $this->belongsTo(User::class, 'triaged_by'); }
    public function charges() { return $this->hasMany(DisciplinaryCharge::class); }
    public function evidence() { return $this->hasMany(DisciplinaryEvidence::class); }
    public function assignments() { return $this->hasMany(DisciplinaryCaseAssignment::class); }
    public function decisions() { return $this->hasMany(DisciplinaryDecision::class); }
    public function sanctions() { return $this->hasMany(DisciplinarySanction::class); }
    public function appeals() { return $this->hasMany(DisciplinaryAppeal::class); }
    public function timeline() { return $this->hasMany(DisciplinaryCaseEvent::class)->orderBy('created_at'); }
}
