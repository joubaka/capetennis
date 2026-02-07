<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
  use HasFactory;
  protected $fillable = [
    'name',
    'num_team_members',
    'year',
    'published',
    'region_id',
    'category_event_id',
    'noProfile',
  ];

  // Always eager load normal + no-profile players
  protected $with = ['team_players', 'team_players_no_profile'];

  public function players()
  {
    return $this->belongsToMany(Player::class, 'team_players')
      ->withPivot('pay_status', 'id', 'rank')
      ->orderBy('rank');
  }
  public function teamPlayers()
  {
    return $this->hasMany(\App\Models\TeamPlayer::class)
      ->orderBy('rank');
  }

  public function team_players()
  {
    return $this->hasMany(TeamPlayer::class, 'team_id', 'id')
      ->orderBy('rank');
  }

  public function team_players_no_profile()
  {
    return $this->hasMany(NoProfileTeamPlayer::class, 'team_id', 'id')
      ->orderBy('rank');
  }

  public function unpayed_players()
  {
    return $this->belongsToMany(Player::class, 'team_players')
      ->withPivot('pay_status')
      ->orderBy('rank')
      ->wherePivot('pay_status', null);
  }

  //to be removed from here


  public function region1()
  {
    return $this->belongsTo(TeamRegion::class, 'region1', 'id');
  }

  public function region2()
  {
    return $this->belongsTo(TeamRegion::class, 'region2', 'id');
  }

  public function no_profile_team() // <- consider renaming
  {
    return $this->hasMany(WpCavDouble::class, 'team_id', 'id')
      ->orderBy('rank');
  }
   ///to here


  public function regions()
  {
    return $this->belongsTo(TeamRegion::class, 'region_id', 'id');
  }

  public function category()
  {
    return $this->belongsTo(CategoryEvent::class, 'category_event_id', 'id');
  }


  /**
   * Merge regular and no-profile players in one accessor.
   */
  public function getAllPlayersAttribute()
  {
    return $this->team_players->concat($this->team_players_no_profile)->sortBy('rank');
  }
  public function allPlayersOrdered()
  {
    // get all 'profile' players
    $profiles = $this->players()
      ->withPivot(['rank', 'pay_status'])
      ->orderBy('team_players.rank', 'asc')
      ->get()
      ->map(function ($p) {
        $p->type = 'profile';
        return $p;
      });

    // get all 'no profile' players
    $noProfiles = $this->team_players_no_profile()
      ->orderBy('rank', 'asc')
      ->get()
      ->map(function ($np) {
        $np->type = 'noprofile';
        return $np;
      });

    // merge and sort by rank again (if needed)
    return $profiles->merge($noProfiles)->sortBy('rank')->values();
  }

  public function clothingOrders()
  {
    return $this->hasMany(\App\Models\ClothingOrder::class, 'team_id', 'id')
      ->with(['items.itemType', 'items.size', 'player']);
  }
}

