<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Payments\Services\RegistrationPaymentService;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\RegistrationOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayfastHandoffController extends Controller
{
    public function events()
    {
        abort_unless(auth()->user()?->hasRole('super-user'), 403);
        $events = Event::query()->whereIn('id', function ($query): void {
            $query->select('category_events.event_id')->from('category_events')
                ->join('registration_order_items', 'registration_order_items.category_event_id', '=', 'category_events.id')
                ->join('registration_orders', 'registration_orders.id', '=', 'registration_order_items.order_id')
                ->whereNotNull('registration_orders.payfast_handed_off_at')
                ->where('registration_orders.pay_status', 0)->where('registration_orders.payfast_paid', 0);
        })->orderByDesc('start_date')->orderByDesc('id')->paginate(20);

        return view('backend.payfast-handoffs.events', compact('events'));
    }

    public function index(Event $event)
    {
        abort_unless(auth()->user()?->hasRole('super-user'), 403);
        $orders = RegistrationOrder::query()->whereNotNull('payfast_handed_off_at')
            ->where('pay_status', 0)->where('payfast_paid', 0)
            ->whereHas('items.category_event', fn (Builder $query) => $query->where('event_id', $event->id))
            ->with('user:id,name,email')->orderBy('payfast_handed_off_at')->orderBy('id')->paginate(20);

        return view('backend.payfast-handoffs.index', compact('event', 'orders'));
    }

    public function release(Request $request, Event $event, RegistrationOrder $order, RegistrationPaymentService $payments)
    {
        abort_unless($request->user()?->hasRole('super-user'), 403);
        $data = $request->validate([
            'evidence_reference' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:\/-]{2,119}$/'],
            'provider_attempt_closed' => ['required', 'accepted'],
            'handed_off_at' => ['required', 'string', 'max:40'],
        ]);

        DB::transaction(function () use ($request, $event, $order, $payments, $data): void {
            $locked = RegistrationOrder::query()->lockForUpdate()->findOrFail($order->id);
            $items = $locked->items()->lockForUpdate()->with('category_event')->get();
            abort_if($items->isEmpty() || $items->contains(fn ($item) => (int) $item->category_event?->event_id !== (int) $event->id), 404);
            if ($locked->payfast_handed_off_at?->toISOString() !== $data['handed_off_at']) {
                throw ValidationException::withMessages(['payment' => 'This payment attempt changed. Refresh and check the current attempt before cancelling checkout.']);
            }
            $locked->setRelation('items', $items);
            $payments->cancelUnresolvedPayfastHandoff($locked, $request->user(), $data['evidence_reference']);
        });

        return redirect()->route('backend.payfast-handoffs.index', $event)
            ->with('success', "Order #{$order->id} cancelled and wallet reservation released. The payer can start registration again from the event.");
    }
}
