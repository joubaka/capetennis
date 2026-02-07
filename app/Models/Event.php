<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use App\Models\EventType;

class Event extends Model
{
  use HasFactory;

  protected $table = 'events';

  /*
  |--------------------------------------------------------------------------
  | MASS ASSIGNMENT
  |--------------------------------------------------------------------------
  */
  protected $fillable = [
    'name',
    'information',
    'start_date',
    'end_date',
    'email',
    'organizer',
    'entryFee',
    'deadline',
    'withdrawal_deadline',
    'eventType',
    'status',
    'venue_notes',
    'logo',
    'published',
    'signUp',
    'series_id',
  ];

  /*
  |--------------------------------------------------------------------------
  | CASTS
  |--------------------------------------------------------------------------
  */
  protected $casts = [
    'published' => 'boolean',
    'signUp' => 'boolean',
    'start_date' => 'date',
    'end_date' => 'date',
    'deadline' => 'int',
    'series_id' => 'int',
    'withdrawal_deadline' => 'datetime',
  ];

  /*
  |--------------------------------------------------------------------------
  | ACCESSORS
  |--------------------------------------------------------------------------
  */
  public function getEntryFeeAttribute()
  {
    return $this->attributes['entryFee'] ?? null;
  }

  /**
   * Legacy registration close logic (days before start_date)
   */
  public function registrationClosesAt(): ?Carbon
  {
    if (!$this->start_date || $this->deadline === null) {
      return null;
    }

    return $this->start_date->copy()->subDays((int) $this->deadline);
  }

  /*
  |--------------------------------------------------------------------------
  | DEADLINE HELPERS
  |--------------------------------------------------------------------------
  */
  public function canWithdraw(): bool
  {
    return !$this->withdrawal_deadline || now()->lte($this->withdrawal_deadline);
  }

  /*
  |--------------------------------------------------------------------------
  | RELATIONSHIPS
  |--------------------------------------------------------------------------
  */
  public function categoryEvents()
  {
    return $this->hasMany(CategoryEvent::class, 'event_id', 'id');
  }

  // Legacy alias – keep for compatibility
  public function eventCategories()
  {
    return $this->categoryEvents();
  }

  public function venues()
  {
    return $this->belongsToMany(
      Venue::class,
      'event_venues',
      'event_id',
      'venue_id'
    );
  }

  public function files()
  {
    return $this->hasMany(File::class, 'event_id', 'id');
  }

  /**
   * IMPORTANT:
   * eventType = COLUMN
   * eventTypeModel = RELATION
   */
  public function eventTypeModel()
  {
    return $this->belongsTo(EventType::class, 'eventType', 'id');
  }

  public function announcements()
  {
    return $this->hasMany(Announcement::class)->latest();
  }

  public function withdrawals()
  {
    return $this->hasMany(Withdrawels::class, 'event_id', 'id');
  }

  public function nominations()
  {
    return $this->hasMany(EventNomination::class, 'event_id', 'id');
  }

  public function registrations()
  {
    return $this->hasManyThrough(
      CategoryEventRegistration::class,
      CategoryEvent::class,
      'event_id',
      'category_event_id',
      'id',
      'id'
    )->orderBy('category_event_id');
  }

  public function series()
  {
    return $this->belongsTo(Series::class, 'series_id', 'id');
  }

  public function sell()
  {
    return $this->belongsTo(SellType::class, 'sell_food', 'id');
  }

  public function admins()
  {
    return $this->belongsToMany(User::class, 'event_admins')
      ->withPivot('event_id');
  }

  public function event_admins()
  {
    return $this->hasMany(EventAdmin::class, 'event_id', 'id');
  }

  public function categories()
  {
    return $this->belongsToMany(Category::class, 'category_events')
      ->withPivot('id');
  }

  public function draws()
  {
    return $this->hasMany(Draw::class, 'event_id', 'id')
      ->orderBy('drawType_id')
      ->orderBy('drawName');
  }

  public function transactions()
  {
    return $this->hasMany(Transaction::class, 'event_id', 'id')
      ->orderByDesc('created_at');
  }

  public function fixtures()
  {
    return $this->hasManyThrough(
      Fixture::class,
      Draw::class,
      'event_id',
      'draw_id',
      'id',
      'id'
    )
      ->orderBy('round')
      ->orderBy('match_nr');
  }

  public function regions()
  {
    return $this->belongsToMany(
      TeamRegion::class,
      'event_regions',
      'event_id',
      'region_id'
    )
      ->withPivot(['id', 'ordering'])
      ->orderBy('event_regions.ordering');
  }

  public function teams()
  {
    return $this->regions()->with('teams');
  }

  /*
  |--------------------------------------------------------------------------
  | EVENT TYPE HELPERS
  |--------------------------------------------------------------------------
  */
  public function isIndividual(): bool
  {
    return (int) $this->eventTypeModel?->type === EventType::INDIVIDUAL;
  }

  public function isTeam(): bool
  {
    return (int) $this->eventTypeModel?->type === EventType::TEAM;
  }

  public function isCamp(): bool
  {
    return (int) $this->eventTypeModel?->type === EventType::CAMP;
  }

  public function getEventBaseTypeAttribute(): ?int
  {
    return $this->eventTypeModel?->type;
  }

  /*
  |--------------------------------------------------------------------------
  | STATUS LABEL (DYNAMIC)
  |--------------------------------------------------------------------------
  */
  public function getStatusLabelAttribute(): string
  {
    if ($this->completed_at) {
      return 'Completed';
    }

    if ($this->fixtures()->exists()) {
      return 'In Progress';
    }

    if ($this->draws()->exists()) {
      return 'Draw Generated';
    }

    if ($this->registrations()->exists()) {
      return 'Entries Open';
    }

    return 'Draft';
  }
}
