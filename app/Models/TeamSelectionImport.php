<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamSelectionImport extends Model
{
    protected $fillable = [
        'source_id', 'event_id', 'region_id', 'series_id', 'ranking_run_id', 'imported_by',
        'status', 'auto_replacement_enabled',
        'response_deadline', 'payment_deadline', 'replacement_payment_deadline', 'email_subject', 'email_message',
        'event_information', 'reply_to', 'include_clothing', 'communication_hash', 'communication_snapshot',
        'prepared_by', 'prepared_at', 'imported_at', 'sent_at',
    ];

    protected $casts = [
        'response_deadline' => 'datetime', 'payment_deadline' => 'datetime', 'replacement_payment_deadline' => 'datetime',
        'auto_replacement_enabled' => 'boolean',
        'include_clothing' => 'boolean', 'communication_snapshot' => 'array', 'prepared_at' => 'datetime',
        'imported_at' => 'datetime', 'sent_at' => 'datetime',
    ];

    public function source() { return $this->belongsTo(EventRegionRankingSource::class, 'source_id'); }
    public function event() { return $this->belongsTo(Event::class); }
    public function region() { return $this->belongsTo(TeamRegion::class); }
    public function series() { return $this->belongsTo(Series::class); }
    public function invitations() { return $this->hasMany(TeamSelectionInvitation::class, 'import_id'); }
}
