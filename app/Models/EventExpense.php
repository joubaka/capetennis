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
        'paid_by_convenor_id',
        'description',
        'amount',
        'quantity',
        'unit_price',
        'recipient_name',
        'budget_amount',
        'receipt_path',
        'date',
        'reimbursed_at',
        'reimbursed_by',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'quantity'     => 'decimal:2',
        'unit_price'   => 'decimal:2',
        'budget_amount' => 'decimal:2',
        'date'         => 'date',
        'reimbursed_at' => 'datetime',
        'approved_at'   => 'datetime',
    ];

    /* ------------------------------------------------------------------ */
    /*  RELATIONSHIPS                                                      */
    /* ------------------------------------------------------------------ */

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function paidByConvenor()
    {
        return $this->belongsTo(EventConvenor::class, 'paid_by_convenor_id');
    }

    public function reimbursedByUser()
    {
        return $this->belongsTo(User::class, 'reimbursed_by');
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /* ------------------------------------------------------------------ */
    /*  HELPERS                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * The effective amount: quantity × unit_price when both are set, otherwise amount.
     */
    public function calculatedAmount(): float
    {
        if ($this->quantity !== null && $this->unit_price !== null) {
            return (float) $this->quantity * (float) $this->unit_price;
        }

        return (float) $this->amount;
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function isReimbursed(): bool
    {
        return $this->reimbursed_at !== null;
    }

    public function hasBudgetVariance(): bool
    {
        return $this->budget_amount !== null;
    }

    /**
     * Positive = under budget, negative = over budget.
     */
    public function budgetVariance(): ?float
    {
        if ($this->budget_amount === null) {
            return null;
        }

        return (float) $this->budget_amount - $this->calculatedAmount();
    }
}
