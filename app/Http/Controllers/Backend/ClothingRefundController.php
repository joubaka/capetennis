<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Finance\Services\RefundRequestService;
use App\Http\Controllers\Controller;
use App\Models\ClothingOrder;
use App\Models\ClothingRefund;
use App\Models\Event;
use App\Services\Clothing\ClothingRefundService;
use Illuminate\Http\Request;

class ClothingRefundController extends Controller
{
    public function show(Request $request, Event $event, ClothingOrder $order, ClothingRefundService $refunds)
    {
        $refunds->authorizeOrder($event, $order, $request->user());
        $order->load(['user', 'player', 'team', 'items.refundItems.refund', 'refunds.items.item']);

        return view('backend.clothing.refunds', compact('event', 'order'));
    }

    public function store(Request $request, Event $event, ClothingOrder $order, RefundRequestService $refunds)
    {
        abort_unless($request->user()?->hasRole('super-user'), 403);
        $validated = $request->validate([
            'request_token' => ['required', 'uuid'],
            'method' => ['required', 'in:wallet,bank,payfast'],
            'reason' => ['required', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);
        $refunds->requestClothingRefund($event, $order, $request->user(), $validated);

        return redirect()->route('backend.clothing.refunds.show', [$event, $order])
            ->with('success', $validated['method'] === 'wallet' ? 'Refund credited to the original payer’s wallet.' : 'Refund reserved. Complete the external payment to the original payer, then record its reference.');
    }

    public function complete(Request $request, Event $event, ClothingOrder $order, ClothingRefund $refund, ClothingRefundService $refunds)
    {
        abort_unless($request->user()?->hasRole('super-user'), 403);
        $validated = $request->validate([
            'reference' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:\/\-]{2,119}$/'],
            'paid_to_original_payer' => ['accepted'],
        ]);
        $refunds->completeExternal($event, $order, $refund, $request->user(), $validated['reference']);

        return redirect()->route('backend.clothing.refunds.show', [$event, $order])->with('success', 'External refund recorded.');
    }
}
