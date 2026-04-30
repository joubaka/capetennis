<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryEvent extends Model
{
    use HasFactory;
  protected $fillable = [
    'event_id',
    'category_id',
    'entry_fee',                 // ✅ ADD THIS
    'ordering',
    'nominations_published',
    'locked_at',
  ];

  public $timestamps = false;

    //used
    public function category()
    {
        return $this->belongsTo(Category::class,'category_id', 'id');
    }
    public function registrations()
    {
        return $this->belongsToMany(Registration::class, 'category_event_registrations', 'category_event_id', 'registration_id')
            ->withPivot('id', 'status', 'withdrawn_at', 'user_id', 'refund_method', 'refund_status', 'refund_gross', 'refund_net', 'refunded_at');
    }
    public function withdrawals()
    {
        return $this->hasMany(Withdrawals::class, 'category_event_id', 'id');
    }
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }

    public function entries()

    {

        return $this->belongsToMany(

            Registration::class,

            'category_event_registrations',

            'category_event_id',

            'registration_id'

        );
    }
  // app/Models/CategoryEvent.php
  public function positions()
  {
    return $this->hasMany(\App\Models\Position::class, 'category_event_id');
  }
  // app/Models/CategoryEvent.php
  public function categoryEventRegistrations()
  {
    return $this->hasMany(\App\Models\CategoryEventRegistration::class, 'category_event_id', 'id')
      ->whereNull('withdrawn_at');
  }

  public function allCategoryEventRegistrations()
  {
    return $this->hasMany(\App\Models\CategoryEventRegistration::class, 'category_event_id', 'id');
  }



  public function teams()
    {
        return $this->hasMany(Team::class, 'category_event_id', 'id');
    }
    public function points()
    {
        return $this->hasMany(Position::class, 'category_event_id', 'id')->orderByDesc('round_robin_score');
    }
    public function nominations()
    {
       return $this->hasMany(EventNomination::class, 'category_event_id', 'id');
    }
    public function draws()
    {
        return $this->hasMany(\App\Models\Draw::class);
    }
  public function results()
  {
    return $this->hasMany(Position::class, 'category_event_id', 'id')
      ->with('registration.players');
  }
  // App\Models\CategoryEvent.php

  public function isLocked(): bool
  {
    return !is_null($this->locked_at);
  }



}
