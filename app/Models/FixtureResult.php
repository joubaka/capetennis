<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixtureResult extends Model
{
    use HasFactory;
  protected $fillable = [
    'fixture_id',
    'result_id',
    'winner_registration',
    'loser_registration',
    'registration1_score',
    'registration2_score',
    'set_nr',
  ];
    public function fixtures()
    {
        return $this->belongsTo(Fixture::class,'fixture_id');
    }
    public function results()
    {
        return $this->belongsTo(Result::class,'result_id');
    }

    public function w_registration()
    {
        return $this->belongsTo(Registration::class,'winner_registration','id');
    }
    public function l_registration()
    {
        return $this->belongsTo(Registration::class,'loser_registration');
    }
}
