<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrawFormatOption extends Model
{
     protected $fillable = [
        'draw_format_id',
        'supports_boxes',
        'supports_playoff',
        'default_boxes',
        'default_playoff_size',
        'default_num_sets',
    ];

    public function format()
    {
        return $this->belongsTo(DrawFormats::class, 'draw_format_id');
    }
}
