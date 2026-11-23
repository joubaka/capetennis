<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PlusEventUpdateState extends Model
{
    protected $guarded = [];
    protected $casts = ['snapshot' => 'array', 'changes' => 'array', 'changed_at' => 'datetime', 'due_at' => 'datetime', 'prepared_at' => 'datetime'];
}
