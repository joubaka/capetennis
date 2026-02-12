<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
  use HasFactory;

  protected $guarded = [];

  /* ---------- Players on this registration (singles/doubles/teams) ---------- */
  public function players()
  {
    return $this->belongsToMany(
      Player::class,
      'player_registrations',
      'registration_id',
      'player_id'
    )->withTimestamps();
  }

  public function playersOrdered()
  {
    return $this->players()->orderBy('surname')->orderBy('name');
  }

  /* ---------- Category events this registration is entered in ---------- */
  public function categoryEvents()
  {
    return $this->belongsToMany(
      CategoryEvent::class,
      'category_event_registrations',
      'registration_id',     // FK on pivot to this model
      'category_event_id'    // FK on pivot to related model
    );
  }

  /* ---------- Draws & groups ---------- */
  public function draws()
  {
    return $this->belongsToMany(
      Draw::class,
      'draw_registrations',
      'registration_id',
      'draw_id'
    )->withPivot('seed');
  }

  public function drawGroups()
  {
    // pivot: draw_group_registrations (registration_id, draw_group_id, seed)
    return $this->belongsToMany(
      DrawGroup::class,
      'draw_group_registrations',
      'registration_id',
      'draw_group_id'
    )->withPivot('seed');
  }

  /* ---------- Order items (bridge: player ↔ category_event ↔ registration) ---------- */
  public function orderItems()
  {
    // table: registration_order_items (id, player_id, order_id, category_event_id, registration_id, ...)
    return $this->hasMany(RegistrationOrderItem::class, 'registration_id', 'id');
  }

  /* ---------- Fixtures / Results convenience ---------- */
  public function fixturesAsOne()
  {
    // fixtures.registration1_id
    return $this->hasMany(Fixture::class, 'registration1_id', 'id');
  }

  public function fixturesAsTwo()
  {
    // fixtures.registration2_id
    return $this->hasMany(Fixture::class, 'registration2_id', 'id');
  }

  /* ---------- Registration-based placements (VIEW) for rankings ---------- */
  public function registrationPlacements()
  {
    // uses SQL view: category_event_registration_placements
    return $this->hasMany(
      CategoryEventRegistrationPlacement::class,
      'registration_id',
      'id'
    );
  }

  /* ---------- Display helpers ---------- */
  public function displayName(): string
  {
    $players = $this->playersOrdered()->get(['name', 'surname']);
    if ($players->isEmpty())
      return 'Unassigned';
    return $players->map(fn($p) => trim("{$p->name} {$p->surname}"))->join(' / ');
  }

  public function getDisplayNameAttribute(): string
  {
    return $this->displayName();
  }
  public function categoryEventRegistrations()
  {
    return $this->hasMany(
      \App\Models\CategoryEventRegistration::class,
      'registration_id',
      'id'
    );
  }


}
