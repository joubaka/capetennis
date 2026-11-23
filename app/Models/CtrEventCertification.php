<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CtrEventCertification extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['certified' => 'boolean'];
}
