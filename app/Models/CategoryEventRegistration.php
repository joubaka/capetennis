<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class CategoryEventRegistration extends Model
{
    use HasFactory;

  protected $fillable = [
    'category_event_id',
    'registration_id',
    'pf_transaction_id',
    'payment_status_id',
    'user_id',
  ];
    //used
  protected $appends = ['display_name'];   // 👈 ADD THIS
    public function registration()
    {
        return $this->belongsTo(Registration::class, 'registration_id', 'id');
    }
    public function categoryEvent()
    {
        return $this->belongsTo(CategoryEvent::class, 'category_event_id', 'id');
    }
 
    public function pf_transaction(){
        return $this->belongsTo(Transaction::class,'pf_transaction_id','pf_payment_id');
    }
 
   

    //not used
  /**
   * 👇 NEW: Players for this category_event_registration
   * category_event_registrations.registration_id
   *   -> player_registrations.registration_id
   *      -> player_registrations.player_id
   *         -> players.id
   */
  public function players()
  {
    return $this->belongsToMany(
      Player::class,
      'player_registrations',  // pivot table
      'registration_id',       // foreignPivotKey on pivot (refers to this model's registration_id)
      'player_id',             // relatedPivotKey on pivot
      'registration_id',       // parent key on this model
      'id'                     // related key on Player
    );
  }
  public function getDisplayNameAttribute()
  {
    if ($this->players->count() === 1) {
      return $this->players->first()->full_name;
    }

    if ($this->players->count() === 2) {
      return $this->players[0]->full_name . ' / ' . $this->players[1]->full_name;
    }

    return 'TBD';
  }

}
