<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrawGroup extends Model
{
  use HasFactory;

  protected $fillable = [
    'draw_id',
    'name',
    'color',
    'category_slug',
  ];

  // -------------------------------------------------------------
  // REGISTRATIONS INSIDE THIS GROUP
  // -------------------------------------------------------------

  // Direct pivot rows (each row = 1 registration + optional seed)
  public function groupRegistrations()
  {
    return $this->hasMany(DrawGroupRegistration::class, 'draw_group_id', 'id')
      ->orderBy('seed')
      ->orderBy('id');
  }

  // Resolved registrations (CategoryEventRegistration)
  public function registrations()
  {
    return $this->belongsToMany(
      Registration::class,
      'draw_group_registrations',
      'draw_group_id',
      'registration_id'
    )
      ->withPivot('seed')
      ->orderByRaw('COALESCE(draw_group_registrations.seed, 9999)');
  }

  // -------------------------------------------------------------
  // PLAYERS (COMPUTED FROM REGISTRATIONS)
  // -------------------------------------------------------------

  public function getPlayersAttribute()
  {
    // Handles singles or doubles (1 or 2 players per registration)
    return $this->registrations
      ->flatMap(fn($r) => $r->players)
      ->unique('id')
      ->values();
  }

  // -------------------------------------------------------------
  // FIXTURES INSIDE THIS GROUP
  // -------------------------------------------------------------

  public function fixtures()
  {
    return $this->hasMany(Fixture::class, 'draw_group_id', 'id')
      ->orderBy('match_nr');
  }

  public function results()
  {
    return $this->hasManyThrough(FixtureResult::class, Fixture::class);
  }

  // -------------------------------------------------------------
  // PARENT DRAW
  // -------------------------------------------------------------

  public function draw()
  {
    return $this->belongsTo(Draw::class, 'draw_id', 'id');
  }
}
