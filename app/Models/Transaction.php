<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;
    protected $table = 'transactions_pf';
    public function player()
    {
        return $this->belongsTo(Player::class, 'player_id', 'id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'custom_int4', 'id');
    }
    public function registration()
    {

        return $this->belongsTo(Registration::class, 'registration_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(RegistrationOrder::class, 'custom_int5', 'id');
    }

    public function category_event()
    {
        return $this->belongsTo(CategoryEvent::class, 'category_event_id', 'id');
    }
 

}
