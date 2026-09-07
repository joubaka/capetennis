<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RankingReviewRecipient extends Model
{
    protected $fillable = [
        'ranking_review_campaign_id', 'email', 'player_ids', 'player_names',
        'category_names', 'status', 'bulk_email_log_id', 'error_message',
    ];

    protected $casts = [
        'player_ids' => 'array',
        'player_names' => 'array',
        'category_names' => 'array',
    ];

    public function campaign()
    {
        return $this->belongsTo(RankingReviewCampaign::class, 'ranking_review_campaign_id');
    }

    public function emailLog()
    {
        return $this->belongsTo(BulkEmailLog::class, 'bulk_email_log_id');
    }
}
