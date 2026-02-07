<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClothingOrder extends Model
{
    use HasFactory;
    protected $fillable = ['player_id', 'team_id', 'pf_id', 'pay_status', 'user_id','user_text','email_text','town_text','total'];

    public function items()
    {
        return $this->hasMany(ClothingOrderItem::class,'clothing_order_id','id');
    }

    public function player(){

        return $this->belongsTo(Player::class,'player_id','id');

    }
    function team()
    {
        return $this->belongsTo(Team::class,'team_id','id');
    }
    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'pf_id', 'pf_payment_id');
    }


}
