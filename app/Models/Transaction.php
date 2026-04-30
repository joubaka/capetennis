<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;
    protected $table = 'transactions_pf';

    protected $casts = [
        'is_test' => 'boolean',
    ];
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
    
    public function category()
    {
        return $this->hasOneThrough(
            Category::class,
            CategoryEvent::class,
            'id',
            'id',
            'category_event_id',
            'category_id'
        );
    }
    
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }
 

}
