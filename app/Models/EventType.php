<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventType extends Model
{
  protected $table = 'eventtypes';

  public const CAMP = 3;
  public const INDIVIDUAL = 1;
  public const TEAM = 2;
}
