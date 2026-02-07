<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExersizeName extends Model
{
    use HasFactory;

    function exersize_type()
    {
        return $this->belongsTo(ExersizeType::class);
    }

}
