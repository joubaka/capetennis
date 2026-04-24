<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventIncomeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'label',
        'quantity',
        'unit_price',
        'total',
        'source',
        'date',
    ];

    protected $casts = [
        'quantity'   => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total'      => 'decimal:2',
        'date'       => 'date',
    ];

    /* ------------------------------------------------------------------ */
    /*  RELATIONSHIPS                                                      */
    /* ------------------------------------------------------------------ */

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /* ------------------------------------------------------------------ */
    /*  HELPERS                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Computed total: quantity × unit_price when both are set, otherwise stored total.
     */
    public function calculatedTotal(): float
    {
        if ($this->quantity !== null && $this->unit_price !== null) {
            return (float) $this->quantity * (float) $this->unit_price;
        }

        return (float) $this->total;
    }
}
