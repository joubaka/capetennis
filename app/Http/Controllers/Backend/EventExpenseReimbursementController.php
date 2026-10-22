<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventExpenseReimbursementController extends Controller
{
    public function store(Request $request, Event $event, FinancialLedgerService $ledger)
    {
        $this->authorize('event-finance.manage', $event);
        $data = $request->validate([
            'convenor_id' => ['required', 'integer', Rule::exists('event_convenors', 'id')->where('event_id', $event->id)],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'paid_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $ledger->recordExpenseReimbursement($event, $request->user(), $data);

        return redirect()->route('admin.events.finances', $event)->with('success', 'Expense reimbursement recorded.');
    }
}
