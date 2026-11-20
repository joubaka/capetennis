<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingContact extends Model
{
    protected $fillable = ['academy', 'name', 'email', 'phone', 'town', 'courts', 'status', 'preference', 'notes'];
}
