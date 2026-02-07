<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrawSetting extends Model
{
    use HasFactory;
    protected $fillable = [
      'draw_id',
      'draw_format_id',
      'draw_type_id',
      'boxes',
      'playoff_size',
      'num_sets',
  ];
  public function drawFormat()
  {
      return $this->belongsTo(\App\Models\DrawFormats::class);
  }

  public function drawType()
  {
      return $this->belongsTo(\App\Models\DrawType::class);
  }

}
