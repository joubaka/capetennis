<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseType extends Model
{
    protected $fillable = ['key', 'label', 'sort_order', 'is_system'];

    protected $casts = ['is_system' => 'boolean'];

    /* ------------------------------------------------------------------
     | Scopes
     * ------------------------------------------------------------------ */

    /**
     * Non-system types (user-managed; payfast / cape_tennis_fee are excluded).
     */
    public function scopeUserManaged($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Ordered for display in dropdowns.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }

    /* ------------------------------------------------------------------
     | Helpers
     * ------------------------------------------------------------------ */

    /**
     * Return all types as key => label array (for use in views / selects).
     */
    public static function asOptions(): array
    {
        return static::ordered()->pluck('label', 'key')->all();
    }
}
