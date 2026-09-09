<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamSelectionInvitation extends Model
{
    public const INVITED = 'invited';
    public const RESERVE = 'reserve';
    public const ACCEPTED_PENDING_PAYMENT = 'accepted_pending_payment';
    public const PAID_CONFIRMED = 'paid_confirmed';
    public const DECLINED = 'declined';
    public const WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'import_id', 'event_id', 'region_id', 'team_id', 'player_id', 'ranking_list_id',
        'order_id', 'ranking_position', 'queue_position', 'total_points', 'roster_rank',
        'status', 'decline_reason', 'declined_by_user_id', 'decline_method',
        'promoted_from_id', 'invited_at', 'accepted_at', 'payment_started_at',
        'paid_at', 'declined_at', 'snapshot_json',
    ];

    protected $casts = [
        'total_points' => 'float', 'roster_rank' => 'integer', 'snapshot_json' => 'array',
        'invited_at' => 'datetime', 'accepted_at' => 'datetime', 'paid_at' => 'datetime',
        'payment_started_at' => 'datetime', 'declined_at' => 'datetime',
    ];

    public function selectionImport() { return $this->belongsTo(TeamSelectionImport::class, 'import_id'); }
    public function event() { return $this->belongsTo(Event::class); }
    public function region() { return $this->belongsTo(TeamRegion::class); }
    public function team() { return $this->belongsTo(Team::class); }
    public function player() { return $this->belongsTo(Player::class); }
    public function rankingList() { return $this->belongsTo(RankingList::class); }
    public function order() { return $this->belongsTo(TeamPaymentOrder::class, 'order_id'); }
    public function declinedBy() { return $this->belongsTo(User::class, 'declined_by_user_id'); }
    public function emailLogs()
    {
        return $this->hasMany(BulkEmailLog::class, 'related_id')
            ->where('related_type', self::class);
    }
}
