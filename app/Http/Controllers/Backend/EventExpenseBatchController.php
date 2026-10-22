<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Finance\Services\EventExpenseBatchService;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventExpense;
use App\Models\ExpenseType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventExpenseBatchController extends Controller
{
    public function index(Event $event)
    {
        $this->authorize('event-finance.manage', $event);

        return view('backend.event.expense-batch', [
            'event' => $event,
            'expenses' => EventExpense::where('event_id', $event->id)
                ->whereNotIn('expense_type', ['payfast', 'cape_tennis_fee'])->orderBy('id')->paginate(40),
            'expenseTypes' => ExpenseType::userManaged()->ordered()->pluck('label', 'key'),
            'venues' => $event->venues()->orderBy('name')->get(),
            'convenors' => $event->convenors()->with('user')->get(),
        ]);
    }

    public function update(Request $request, Event $event, EventExpenseBatchService $service)
    {
        $this->authorize('event-finance.manage', $event);
        $attributes = ['form_complete' => 'expense list', 'lines' => 'expense list'];
        $submitted = $request->input('lines');
        if (is_array($submitted)) {
            foreach (array_keys($submitted) as $position => $key) {
                foreach (['id' => 'entry', 'version' => 'saved version', 'batch_key' => 'new entry reference',
                    'expense_type' => 'category', 'description' => 'description', 'recipient_name' => 'payee',
                    'paid_by_convenor_id' => 'paid by', 'venue_id' => 'venue', 'quantity' => 'quantity',
                    'unit_price' => 'unit cost', 'amount' => 'amount', 'date' => 'date'] as $field => $label) {
                    $attributes["lines.$key.$field"] = 'Expense '.($position + 1).' '.$label;
                }
            }
        }
        $data = $request->validate([
            'form_complete' => ['required', 'in:1'],
            'lines' => ['present', 'nullable', 'array', 'max:70'],
            'lines.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('event_expenses', 'id')->where('event_id', $event->id)],
            'lines.*.version' => ['required_with:lines.*.id', 'nullable', 'string', 'size:64'],
            'lines.*.batch_key' => ['required_without:lines.*.id', 'nullable', 'uuid', 'distinct'],
            'lines.*.expense_type' => ['required', 'string', Rule::exists('expense_types', 'key')->where('is_system', false), Rule::notIn(['payfast', 'cape_tennis_fee'])],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.recipient_name' => ['nullable', 'string', 'max:150'],
            'lines.*.paid_by_convenor_id' => ['nullable', 'integer', Rule::exists('event_convenors', 'id')->where('event_id', $event->id)],
            'lines.*.venue_id' => ['nullable', 'integer', Rule::in($event->venues()->pluck('venues.id')->all())],
            'lines.*.quantity' => ['nullable', 'required_with:lines.*.unit_price', 'numeric', 'decimal:0,2', 'min:0', 'max:100000'],
            'lines.*.unit_price' => ['nullable', 'required_with:lines.*.quantity', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'lines.*.amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'lines.*.date' => ['required', 'date_format:Y-m-d'],
        ], [
            'form_complete.required' => 'The expense list was incomplete. Reload the page and try again.',
        ], $attributes);
        $service->save($event, $data['lines'] ?? []);

        return redirect()->route('admin.events.expenses.edit', ['event' => $event, 'page' => max(1, $request->integer('page', 1))])
            ->with('success', 'Expenses saved.');
    }
}
