<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventNomination extends Model
{
  use HasFactory;

  protected $table = 'event_nominations';

  protected $fillable = [
    'event_id',
    'category_event_id',
    'player_id',
    'nominee_name',
    'nominee_surname',
    'nominee_email',
    'profileless_key',
  ];

  // Relationships
  public function getDisplayNameAttribute(): string
  {
    return trim(($this->player?->name ?? $this->nominee_name).' '.($this->player?->surname ?? $this->nominee_surname));
  }

  public function player()
  {
    return $this->belongsTo(Player::class);
  }

  public function category_event()
  {
    return $this->belongsTo(CategoryEvent::class);
  }

  public function categoryEvent()
  {
    return $this->belongsTo(CategoryEvent::class, 'category_event_id');
  }

  public function event()
  {
    return $this->belongsTo(Event::class);
  }

  public function actionableInvitation()
  {
    return $this->hasOne(InterprovincialTrialInvitation::class, 'nomination_id')
      ->ofMany(['id' => 'max'], fn ($query) => $query->whereIn('status', [
        'queued', 'sent', 'open_registration', 'accepted_pending_payment', 'paid_confirmed', 'declined', 'withdrawn',
      ]));
  }
}
