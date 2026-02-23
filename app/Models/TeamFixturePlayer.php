<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamFixturePlayer extends Model
{
    use HasFactory;

    protected $table = 'team_fixture_players';

    protected $fillable = [
      'team_fixture_id',
      'team1_id',
      'team2_id',
      'team1_no_profile_id',
      'team2_no_profile_id',
    ];

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
