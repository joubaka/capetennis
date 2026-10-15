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

        $providerAttempts = \Illuminate\Support\Facades\DB::table('clothing_provider_refund_attempts')->whereIn('clothing_refund_id', $order->refunds->modelKeys())->get()->keyBy('clothing_refund_id');

        return view('backend.clothing.refunds', compact('event', 'order', 'providerAttempts'));
    }

    public function store(Request $request, Event $event, ClothingOrder $order, RefundRequestService $refunds)
    {
        abort_unless($request->user()?->hasRole('super-user'), 403);
        // Bank details are used only for this provider call, never stored or flashed back.
        $bankInput = $request->input('bank_details', []);
        $request->request->remove('bank_details');
        $request->query->remove('bank_details');
        if ($request->isJson()) {
            $request->json()->remove('bank_details');
        }
        $bankDetails = [];
        if (is_array($bankInput) && array_filter($bankInput, fn ($value) => $value !== null && $value !== '') !== []) {
            $bankDetails = \Illuminate\Support\Facades\Validator::make($bankInput, [
                'account_holder' => ['required', 'string', 'max:100'],
                'bank_name' => ['required', 'string', 'max:100'],
                'branch_code' => ['required', 'regex:/^[0-9]{4,6}$/'],
                'account_number' => ['required', 'regex:/^[0-9]{5,12}$/'],
                'account_type' => ['required', 'in:current,savings'],
                'original_payer' => ['accepted'],
            ])->validate();
            unset($bankDetails['original_payer']);
        }
        $validated = $request->validate([
            'request_token' => ['required', 'uuid'],
            'method' => ['required', 'in:wallet,payfast'],
            'reason' => ['required', 'string', 'max:500'],
            'mode' => ['sometimes', 'in:quantity,amount,full'],
            'item_id' => ['required_if:mode,amount,full', 'integer'],
            'amount' => ['required_if:mode,amount', 'regex:/^[0-9]{1,8}(?:\.[0-9]{1,2})?$/'],
            'items' => ['sometimes', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);
        $refund = $refunds->requestClothingRefund($event, $order, $request->user(), $validated);
        if ($validated['method'] === 'payfast') {
            app(\App\Services\Clothing\ClothingPayfastRefundService::class)->dispatch($refund, $request->user(), $bankDetails);
        }

        return redirect()->route('backend.clothing.refunds.show', [$event, $order])
            ->with('success', $validated['method'] === 'wallet' ? 'Refund credited to the original payer’s wallet.' : 'PayFast refund completed to the original payer.');
    }

    public function retry(Request $request, Event $event, ClothingOrder $order, ClothingRefund $refund, ClothingRefundService $refunds)
    {
        $refunds->authorizeOrder($event, $order, $request->user());
        abort_unless((int) $refund->clothing_order_id === (int) $order->id && (int) $refund->event_id === (int) $event->id, 404);
        app(\App\Services\Clothing\ClothingPayfastRefundService::class)->dispatch($refund, $request->user());

        return redirect()->route('backend.clothing.refunds.show', [$event, $order])->with('success', 'PayFast refund reconciled.');
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
