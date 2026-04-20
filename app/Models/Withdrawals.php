<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Withdrawals extends Model
{
    use HasFactory;

    protected $table = 'withdrawels';

    public function registration()
    {
        return $this->belongsTo(Registration::class, 'registration_id', 'id');
    }
}
