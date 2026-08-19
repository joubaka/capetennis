<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class Player extends Model
{
  use HasFactory;

  protected static ?bool $hasIdentityHashColumns = null;

  protected $fillable = [
    'name',
    'surname',
    'cellNr',
    'gender',
    'userId',
    'email',
    'dateOfBirth',
    'coach',
    'profile_updated_at',
    'profile_complete',
  ];

  protected $casts = [
    'profile_updated_at' => 'datetime',
    'profile_complete' => 'boolean',
  ];

  protected $hidden = [
    'identity_name_hash',
    'identity_email_dob_hash',
    'identity_cell_dob_hash',
  ];

  protected $appends = ['full_name'];

  protected static function booted(): void
  {
    static::saving(function (Player $player) {
      self::$hasIdentityHashColumns ??= Schema::hasColumn('players', 'identity_name_hash');
      if (!self::$hasIdentityHashColumns) {
        return;
      }

      $name = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $player->name)));
      $surname = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $player->surname)));
      $dob = substr((string) $player->dateOfBirth, 0, 10);
      $email = mb_strtolower(trim((string) $player->email));
      $cell = preg_replace('/[^0-9+]/', '', (string) $player->cellNr) ?? '';

      $player->identity_name_hash = $name !== '' && $surname !== '' ? hash('sha256', $name.'|'.$surname) : null;
      $player->identity_email_dob_hash = $dob !== '' && $email !== '' ? hash('sha256', $dob.'|'.$email) : null;
      $player->identity_cell_dob_hash = $dob !== '' && $cell !== '' ? hash('sha256', $dob.'|'.$cell) : null;
    });
  }

  /**
   * Check if player profile needs updating (hasn't been updated in the last year)
   */
  public function needsProfileUpdate(): bool
  {
    // If never updated, needs update
    if (!$this->profile_updated_at) {
      return true;
    }

    // If updated more than 1 year ago, needs update
    return $this->profile_updated_at->lt(Carbon::now()->subYear());
  }

  /**
   * Check if profile is complete (has all required fields)
   */
  public function isProfileComplete(): bool
  {
    return !empty($this->name)
      && !empty($this->surname)
      && !empty($this->dateOfBirth)
      && !empty($this->gender)
      && !empty($this->cellNr);
  }

  /**
   * Mark profile as updated
   */
  public function markProfileUpdated(): void
  {
    $this->update([
      'profile_updated_at' => Carbon::now(),
      'profile_complete' => $this->isProfileComplete(),
    ]);
  }

  /**
   * Get profile status for display
   */
  public function getProfileStatus(): array
  {
    $needsUpdate = $this->needsProfileUpdate();
    $isComplete = $this->isProfileComplete();

    if (!$isComplete) {
      return [
        'status' => 'incomplete',
        'badge' => 'danger',
        'message' => 'Profile incomplete - please update',
        'icon' => 'ti-alert-circle',
      ];
    }

    if ($needsUpdate) {
      return [
        'status' => 'outdated',
        'badge' => 'warning',
        'message' => 'Profile needs annual update',
        'icon' => 'ti-clock',
      ];
    }

    return [
      'status' => 'current',
      'badge' => 'success',
      'message' => 'Profile up to date',
      'icon' => 'ti-check',
    ];
  }

  //used
  public function positions($id)
  {
    return $this->hasMany(Position::class, 'player_id', 'id')->where('category_event_id', $id)->first();
  }
  public function exercises()
  {
    return $this->hasMany(Exercise::class);
  }
  public function practices()
  {
    return $this->hasMany(Practice::class);
  }


  function practiceMatches()
  {
    return $this->hasManyThrough(PracticeFixtures::class, Practice::class, 'player_id', 'practice_id', 'id', 'id');
  }
  public function allPositions()
  {
    return $this->hasMany(Position::class, 'player_id', 'id');
  }

  public function getFullNameAttribute()
  {

    return $this->name . ' ' . $this->surname;
  }

  public function team()
  {

    return $this->hasMany(TeamPlayer::class, 'player_id', 'id');
  }

  public function rankings()
  {

    return $this->hasMany(RankingScores::class, 'player_id', 'id');
  }

  public function registrations_order_items()
  {

    return $this->hasMany(RegistrationOrderItems::class, 'player_id', 'id');
  }
  public function user()
  {
    return $this->belongsTo(User::class, 'userId', 'id');
  }

  public function users()
  {
    return $this->belongsToMany(User::class, 'user_players', 'player_id', 'user_id');
  }
  public function subscriptions()
  {
    return $this->belongsToMany(Subscription::class, 'player_subscriptions', 'player_id', 'subscription_id');
  }

  public function goals($id)
  {
    return $this->hasMany(Goal::class)->where('goal_type_id', $id)->get();
  }


  //not used

  public function registrations()
  {
    return $this->belongsToMany(Registration::class, 'player_registrations', 'player_id', 'registration_id');
  }


  public function practice_sets()
  {
    return $this->hasMany(PracticeFixtures::class, 'registration1_id', 'id')
      ->orWhere('registration2_id', $this->id);
  }

  public function sets_won()
  {
    return $this->hasMany(PracticeResults::class, 'winner_registration', 'id');
  }
  public function sets_lost()
  {
    return $this->hasMany(PracticeResults::class, 'loser_registration', 'id');
  }

  public function teams()
  {
    return $this->belongsToMany(Team::class, 'team_players', 'player_id', 'team_id');
  }


  public function ts()
  {
    return $this->belongsToMany(Team::class, 'team_players', 'player_id', 'team_id');
  }
  public function position_series()
  {

    return $this->hasMany(Position::class, 'player_id', 'id');
  }

  public function matches_won_in_event()
  {
    return $this->hasMany(Result::class, 'winner_registration', 'id')->get();
  }

  public function clothingOrder()
  {
    return $this->hasOne(ClothingOrder::class, 'player_id', 'id');
  }

  public function teamResultsTeam1()
  {
    return $this->hasMany(TeamFixturePlayer::class, 'team1_id', 'id');
  }
  public function teamResultsTeam2()
  {
    return $this->hasMany(TeamFixturePlayer::class, 'team2_id', 'id');
  }

  public function teamFixtures()
  {
    return $this->hasMany(TeamFixture::class, 'team_fixture_id', 'id');
  }
  public function shortName()
  {
    return $this->name . ' ' . strtoupper(substr($this->surname, 0, 1)) . '.';
  }

  public function isMinor()
  {
    return $this->dateOfBirth && \Carbon\Carbon::parse($this->dateOfBirth)->age < 18;
  }

  public function hasAcceptedLatestAgreement()
  {
    $agreement = Agreement::where('is_active', 1)->latest()->first();

    if (!$agreement) {
      return true; // No active agreement, nothing to accept
    }

    return PlayerAgreement::where('player_id', $this->id)
      ->where('agreement_id', $agreement->id)
      ->exists();
  }

  public function agreements()
  {
    return $this->hasMany(PlayerAgreement::class);
  }

  public function violations()
  {
    return $this->hasMany(PlayerViolation::class);
  }

  public function suspensions()
  {
    return $this->hasMany(PlayerSuspension::class);
  }

}
