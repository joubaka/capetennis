<?php

namespace App\Http\Controllers\Frontend;

use App\Domain\Entries\Services\CategoryAdjustmentService;
use App\Http\Controllers\Controller;
use App\Models\{CategoryAdjustment, CategoryEvent, CategoryEventRegistration};
use Illuminate\Http\Request;

class RegistrationCategoryMoveController extends Controller
{
    public function __construct(private CategoryAdjustmentService $adjustments) {}

    public function quote(Request $request, $entryId)
    {
        $request->validate(['new_category_event_id' => ['required', 'integer', 'exists:category_events,id']]);
        return response()->json($this->adjustments->quote(CategoryEventRegistration::findOrFail($entryId),
            CategoryEvent::findOrFail($request->new_category_event_id), $request->user()));
    }

    public function move(Request $request, $entryId)
    {
        $valid = $request->validate([
            'new_category_event_id' => ['required', 'integer', 'exists:category_events,id'],
            'quote_token' => ['required', 'string'], 'request_token' => ['required', 'uuid'],
        ]);
        $adjustment = $this->adjustments->start(CategoryEventRegistration::findOrFail($entryId),
            CategoryEvent::findOrFail($valid['new_category_event_id']), $request->user(), $valid['quote_token'], $valid['request_token']);
        return response()->json(['success' => true,
            'message' => $adjustment->applied_at ? ((float) $adjustment->wallet_credit > 0
                ? 'Category changed. R'.number_format((float) $adjustment->wallet_credit, 2).' was credited to your wallet.'
                : 'Category changed.') : 'Pay the difference to complete your category change.',
            'checkout_url' => $adjustment->applied_at ? null : route('registrations.categoryAdjustment.show', $adjustment),
        ]);
    }

    public function show(Request $request, CategoryAdjustment $adjustment)
    {
        abort_unless((int) $adjustment->user_id === (int) $request->user()->id, 403);
        if ($adjustment->status === 'pending' && $adjustment->expires_at->isPast()) {
            $adjustment = $this->adjustments->cancel($adjustment, $request->user(), true);
        }
        return view('frontend.registrations.category-adjustment', compact('adjustment'));
    }

    public function pay(Request $request, CategoryAdjustment $adjustment)
    {
        $adjustment = $this->adjustments->handoff($adjustment, $request->user());
        $form = $this->adjustments->form($adjustment);
        return view('frontend.registrations.category-adjustment-handoff', compact('adjustment', 'form'));
    }

    public function cancel(Request $request, CategoryAdjustment $adjustment)
    {
        $this->adjustments->cancel($adjustment, $request->user());
        return redirect()->route('registrations.categoryAdjustment.show', $adjustment)->with('success', 'Category checkout closed.');
    }
}
