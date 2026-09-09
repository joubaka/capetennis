<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClothingOrder extends Model
{
    use HasFactory;
    protected $fillable = [
        'player_id', 'team_id', 'event_id', 'pf_id', 'pay_status', 'user_id',
        'user_text', 'email_text', 'town_text', 'total', 'status', 'request_token',
        'payfast_paid', 'payfast_amount_due', 'payfast_pf_payment_id',
        'wallet_reserved', 'wallet_debited', 'payment_method', 'paid_at', 'amount_paid',
    ];

    protected $casts = [
        'pay_status' => 'integer',
        'payfast_paid' => 'boolean',
        'wallet_debited' => 'boolean',
        'total' => 'decimal:2',
        'payfast_amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(ClothingOrderItem::class,'clothing_order_id','id');
    }

    public function player(){

        return $this->belongsTo(Player::class,'player_id','id');

    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function event()
    {
        return $this->belongsTo(Event::class);
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
