<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrawGroupRegistration extends Model
{
  use HasFactory;

  protected $table = 'draw_group_registrations';

  protected $fillable = [
    'draw_group_id',
    'registration_id',
    'seed'
  ];

  // -------------------------------------------------------------
  // THE GROUP THIS BELONGS TO
  // -------------------------------------------------------------

  public function group()
  {
    return $this->belongsTo(DrawGroup::class, 'draw_group_id', 'id');
  }

  // -------------------------------------------------------------
  // CATEGORY EVENT REGISTRATION (ENTRY)
  // -------------------------------------------------------------

  public function registration()
  {
    return $this->belongsTo(Registration::class, 'registration_id', 'id');
  }


  // -------------------------------------------------------------
  // DIRECT PLAYER (SINGLES OR FIRST PLAYER IN DOUBLES)
  // -------------------------------------------------------------

  public function player()
  {
    return $this->registration->players->first() ?? null;
  }
}
