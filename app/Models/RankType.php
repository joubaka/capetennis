<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RankType extends Model
{
  use HasFactory;

  protected $table = 'rank_types';

  protected $fillable = [
    'type',
    'name',
    'description',
  ];

  /* =========================
   | TYPE CONSTANTS
   ========================= */
  public const WILSON_SERIES = 'wilson_series';
  public const PLATTELAND_SERIES = 'platteland_series';
  public const JTA_PARTICIPATION = 'jta_participation';

  /* =========================
   | RELATIONSHIPS
   ========================= */
  public function series()
  {
    return $this->hasMany(Series::class, 'rank_type');
  }

  /* =========================
   | HELPERS
   ========================= */
  public function isType(string $type): bool
  {
    return $this->type === $type;
  }

}
