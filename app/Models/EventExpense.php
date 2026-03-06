<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'expense_type',
        'convenor_name',
        'description',
        'amount',
        'date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
    ];

    /**
     * Get the event that owns this expense.
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
