<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhotoProduct extends Model
{
    use HasFactory;
    protected $with = ['options'];

    public function options()
    {
        return $this->hasMany(PhotoProductOption::class,'photo_product_id','id');
    }

}
