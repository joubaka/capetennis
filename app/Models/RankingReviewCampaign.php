<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RankingReviewCampaign extends Model
{
    protected $fillable = [
        'uuid', 'series_id', 'run_id', 'snapshot_hash', 'status', 'subject',
        'message', 'reply_to', 'cutoff_at', 'sent_by', 'sent_at',
        'finalized_at', 'finalized_by', 'player_count', 'recipient_count',
        'missing_email_count',
    ];

    protected $casts = [
        'cutoff_at' => 'datetime',
        'sent_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function series()
    {
        return $this->belongsTo(Series::class);
    }

    public function recipients()
    {
        return $this->hasMany(RankingReviewRecipient::class);
    }
}
