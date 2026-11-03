<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlusPublicationState extends Model
{
    protected $guarded = [];
    protected $casts = ['snapshot' => 'array', 'changes' => 'array', 'due_at' => 'datetime', 'prepared_at' => 'datetime'];
}
