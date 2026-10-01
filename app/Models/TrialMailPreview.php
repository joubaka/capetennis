<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TrialMailPreview extends Model { protected $guarded=['id']; protected $casts=['options'=>'array','recipients'=>'array','excluded'=>'array','expires_at'=>'datetime','committed_at'=>'datetime']; }
