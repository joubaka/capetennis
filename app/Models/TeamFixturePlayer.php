<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Log;

class TeamFixturePlayer extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'team_fixture_players';

    protected $fillable = [
      'team_fixture_id',
      'team1_id',
      'team2_id',
      'team1_no_profile_id',
      'team2_no_profile_id',
    ];

    protected static function booted()
    {
        static::updated(function ($model) {
            Log::debug('🟢 TeamFixturePlayer UPDATED', [
                'id' => $model->id,
                'changes' => $model->getChanges(),
            ]);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            // ✅ Include no-profile columns too
            ->logOnly([
                'team1_id', 
                'team2_id', 
                'team1_no_profile_id', 
                'team2_no_profile_id',
                'team_fixture_id'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('fixture_player_assignment');
    }

    public function fixture()
    {
      return $this->belongsTo(TeamFixture::class, 'team_fixture_id');
    }

    public function player1()
    {
      return $this->belongsTo(Player::class, 'team1_id');
    }

    public function player2()
    {
      return $this->belongsTo(Player::class, 'team2_id');
    }

    public function noProfile1()
    {
      return $this->belongsTo(NoProfileTeamPlayer::class, 'team1_no_profile_id');
    }

    public function noProfile2()
    {
      return $this->belongsTo(NoProfileTeamPlayer::class, 'team2_no_profile_id');
    }
}
