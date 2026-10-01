<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TrialMailSchedule extends Model { protected $guarded=['id']; protected $casts=['options'=>'array','next_send_at'=>'datetime','stop_at'=>'datetime','active'=>'boolean']; }
