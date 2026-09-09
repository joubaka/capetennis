<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamRegion extends Model
{
  use HasFactory;

  protected $fillable = [
    'region_name',
    'short_name',
    'clothing_order',
    'clothing_admin',
    'no_profile',
    'region_fee',
  ];

  // ------------------------------
  // ✅ Relationships
  // ------------------------------

  public function teams()
  {
    return $this->hasMany(Team::class, 'region_id', 'id')->orderBy('name');
  }

  public function teams_per_age($age)
  {
    return $this->hasMany(Team::class, 'region_id', 'id')
      ->where('name', 'like', "%$age%")
      ->orderBy('name');
  }

  public function teamPlayers()
  {
    return $this->hasManyThrough(TeamPlayer::class, Team::class);
  }

  public function events()
  {
    return $this->belongsToMany(Event::class, 'event_regions', 'region_id', 'event_id')
      ->withPivot('ordering');
  }

  public function event()
  {
    return $this->belongsTo(Event::class);
  }

  public function fixturesReg1()
  {
    return $this->hasMany(TeamFixture::class, 'region1', 'id');
  }

  public function fixturesReg2()
  {
    return $this->hasMany(TeamFixture::class, 'region2', 'id');
  }

  public function clothingItems()
  {
    return $this->hasMany(\App\Models\ClothingItemType::class, 'region_id');
  }

  // ------------------------------
  // ✅ NEW: Clothing Helpers
  // ------------------------------

  /**
   * Determine if clothing ordering is enabled for this region.
   *
   * @return bool
   */
  public function isClothingEnabled(): bool
  {
    return (bool) $this->clothing_order;
  }

  /**
   * Read the legacy clothing participation flag.
   */
  public function isClothingAdmin(): bool
  {
    return (bool) $this->clothing_admin;
  }

  /**
   * Determine whether this region participates in online clothing ordering.
   *
   * Older catalogues pre-date the explicit selection. For those nullable rows,
   * retain their existing behaviour when a catalogue or open-order flag exists.
   */
  public function usesOnlineClothingOrders(): bool
  {
    if ($this->clothing_admin !== null) {
      return (bool) $this->clothing_admin;
    }

    if ((bool) $this->clothing_order) {
      return true;
    }

    return $this->relationLoaded('clothingItems')
      ? $this->clothingItems->isNotEmpty()
      : $this->clothingItems()->exists();
  }

  /**
   * Combined accessor — returns true if either order or admin mode is active.
   *
   * @return bool
   */
  public function hasClothingAccess(): bool
  {
    return $this->isClothingEnabled() || $this->usesOnlineClothingOrders();
  }
}
