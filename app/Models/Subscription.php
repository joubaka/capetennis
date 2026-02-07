<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    public function info()
    {
        return $this->belongsTo(SubscriptionInfo::class,'subscription_info_id','id');
    }
}
