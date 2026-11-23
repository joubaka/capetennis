<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PlusEventUpdateDelivery extends Model
{
    protected $guarded = [];
    protected $casts = ['snapshot' => 'array', 'changes' => 'array', 'player_ids' => 'array', 'claimed_at' => 'datetime', 'sent_at' => 'datetime'];
}
