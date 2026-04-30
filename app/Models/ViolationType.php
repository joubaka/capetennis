<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ViolationType extends Model
{
    protected $fillable = ['name', 'category', 'default_points', 'description', 'active'];

    protected $casts = [
        'default_points' => 'integer',
        'active'         => 'boolean',
    ];

    public static array $categories = [
        'on_court'   => 'On-Court',
        'withdrawal' => 'Withdrawal',
        'no_show'    => 'No Show',
        'abuse'      => 'Abuse',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function violations()
    {
        return $this->hasMany(PlayerViolation::class);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::$categories[$this->category] ?? ucfirst($this->category);
    }
}
