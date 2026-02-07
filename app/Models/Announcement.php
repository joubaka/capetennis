<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
  use HasFactory, SoftDeletes;

  protected $fillable = [
    'event_id',
    'title',
    'message',
  ];

  public function event()
  {
    return $this->belongsTo(Event::class);
  }
  public function getIsHiddenAttribute(): bool
  {
    return $this->trashed();
  }

}
