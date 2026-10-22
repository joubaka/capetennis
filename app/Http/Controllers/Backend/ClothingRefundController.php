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
        $refunds->authorizeOrder($event, $order, $request->user(), false);
        $relations = ['user', 'player', 'team', 'items.refundItems.refund', 'refunds.items.item', 'refunds.requestedBy', 'refunds.completedBy'];
        if (\Illuminate\Support\Facades\Schema::hasTable('clothing_refund_historical_imports')) {
            $relations[] = 'refunds.historicalImport';
        }
        $order->load($relations);

        $providerAttempts = \Illuminate\Support\Facades\DB::table('clothing_provider_refund_attempts')->whereIn('clothing_refund_id', $order->refunds->modelKeys())->get()->keyBy('clothing_refund_id');

        return view('backend.clothing.refunds', compact('event', 'order', 'providerAttempts'));
    }

    public function store(Request $request, Event $event, ClothingOrder $order, RefundRequestService $refunds)
    {
        app(ClothingRefundService::class)->authorizeOrder($event, $order, $request->user());
        $request->headers->set('referer', route('backend.clothing.refunds.show', [$event, $order]));
        $bankDetails = $this->bankDetails($request);
        $request->validate(['review_token' => ['required', 'string', 'max:30000']]);
        $validated = app(\App\Services\Clothing\ClothingRefundReviewService::class)->resolve($request->input('review_token'), $event, $order, $request->user());
        // Only the reviewed values determine the financial mutation.
        foreach (['method', 'items', 'mode', 'amount', 'item_id', 'request_token', 'reason', 'late_refund_note'] as $field) {
            if ($request->exists($field)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['refund' => 'Confirm the reviewed refund without changing its selection. Review again to make changes.']);
            }
        }
        $refund = $refunds->requestClothingRefund($event, $order, $request->user(), $validated);
        if ($validated['method'] === 'payfast') {
            app(\App\Services\Clothing\ClothingPayfastRefundService::class)->dispatch($refund, $request->user(), $bankDetails, $validated['late_refund_note'] ?? null);
        }
        return $this->completedResponse($request, $event, $order, $refund, $validated);
    }

    private function bankDetails(Request $request): array
    {
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
        return $bankDetails;
    }

    private function completedResponse(Request $request, Event $event, ClothingOrder $order, ClothingRefund $refund, array $validated = [])
    {
        $refund->refresh()->load(['payer', 'items.item']);
        if ($refund->refund_status !== 'completed') {
            return redirect()->route('backend.clothing.refunds.show', [$event, $order])->withErrors(['refund' => 'This refund is still pending. Check its history before taking further action.']);
        }
        $parameters = ['region' => $order->team->region_id, 'event_id' => $event->id,
            'sort' => $validated['return_sort'] ?? 'rank', 'search' => $validated['return_search'] ?? '', 'team' => $validated['return_team'] ?? ''];
        if (($validated['return_all_events'] ?? false) && $request->user()->hasRole('super-user')) unset($parameters['event_id']);
        $response = redirect()->route('backend.region.clothing.orders', $parameters);
        $shown = $request->session()->get('clothing_refund_receipts', []);
        if (! in_array($refund->id, $shown, true)) {
            $response->with('clothing_refund_completed', ['id' => $refund->id, 'order_id' => $refund->clothing_order_id,
                'event_id' => $refund->event_id, 'region_id' => $order->team->region_id, 'payer' => $refund->payer?->name,
                'net' => $refund->refund_net, 'fee' => $refund->refund_fee, 'method' => $refund->refund_method,
                'items' => $refund->items->map(fn ($line) => ['name' => $line->item?->item_name, 'size' => $line->item?->size_name,
                    'quantity' => $line->quantity, 'amount' => $line->amount])->all()]);
            $request->session()->put('clothing_refund_receipts', array_slice([...$shown, $refund->id], -50));
        }
        return $response;
    }

    public function review(Request $request, Event $event, ClothingOrder $order, \App\Services\Clothing\ClothingRefundReviewService $reviews)
    {
        app(ClothingRefundService::class)->authorizeOrder($event, $order, $request->user());
        $request->headers->set('referer', route('backend.clothing.refunds.show', [$event, $order]));
        // Review never accepts, stores or flashes personal banking details.
        $request->request->remove('bank_details');
        $request->query->remove('bank_details');
        if ($request->isJson()) $request->json()->remove('bank_details');
        $validated = $request->validate([
            'request_token' => ['required', 'uuid'],
            'return_sort' => ['nullable', 'in:rank,name,team,age_group,date_desc,date_asc'],
            'return_search' => ['nullable', 'string', 'max:120'],
            'return_team' => ['nullable', 'integer'],
            'return_all_events' => ['nullable', 'boolean'],
            'method' => ['required', 'in:wallet,payfast'],
            'late_refund_note' => ['nullable', 'string', 'max:1000'],
            'reason' => ['required', 'string', 'max:500'],
            'mode' => ['sometimes', 'in:quantity,amount,full'],
            'item_id' => ['required_if:mode,amount,full', 'integer'],
            'amount' => ['required_if:mode,amount', 'regex:/^[0-9]{1,8}(?:\.[0-9]{1,2})?$/'],
            'items' => ['sometimes', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);
        if (! empty($validated['return_team']) && ! \App\Models\Team::whereKey($validated['return_team'])->where('region_id', $order->team->region_id)
            ->whereHas('category', fn ($query) => $query->where('event_id', $event->id))->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['return_team' => 'Choose a team in this event and region.']);
        }
        $quote = $reviews->quote($order, $validated);
        $deadline = app(ClothingRefundService::class)->deadline($event, $order);
        if ($deadline && now()->gte($deadline) && trim($validated['late_refund_note'] ?? '') === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['late_refund_note' => 'Explain the late refund and the clothing payout adjustment to reconcile.']);
        }
        $reviewToken = $reviews->token($event, $order, $request->user(), $validated, $quote);
        // Render the read-only review in this response. Background audit requests
        // can save an older session payload; the financial intent must not depend
        // on a token surviving a separate session write and redirect.
        $order->load('user');
        return response()->view('backend.clothing.refund-review', compact('event', 'order', 'quote', 'validated', 'reviewToken'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function reviewPage(Request $request, Event $event, ClothingOrder $order)
    {
        app(ClothingRefundService::class)->authorizeOrder($event, $order, $request->user());
        return redirect()->route('backend.clothing.refunds.show', [$event, $order]);
    }

    public function checkPayfast(Request $request, Event $event, ClothingOrder $order, ClothingRefund $refund, \App\Services\Clothing\ClothingPayfastRefundRecoveryService $recovery)
    {
        abort_unless($request->user()->hasRole('super-user'), 403);
        app(ClothingRefundService::class)->authorizeOrder($event, $order, $request->user());
        abort_unless((int) $refund->clothing_order_id === (int) $order->id && (int) $refund->event_id === (int) $event->id, 404);
        $request->headers->set('referer', route('backend.clothing.refunds.show', [$event, $order]));
        $preview = $recovery->preview($refund, $request->user());
        $recovery->apply($refund, $request->user(), $preview['confirmation_token']);
        return redirect()->route('backend.clothing.refunds.show', [$event, $order])
            ->with('success', 'PayFast confirms no refund was paid or is pending. No money moved. You can now review a wallet refund or retry PayFast.');
    }

    public function walletReview(Request $request, Event $event, ClothingOrder $order, ClothingRefund $refund, \App\Services\Clothing\ClothingRefundWalletFallbackService $fallback)
    {
        $data = $request->validate(['late_refund_note' => ['nullable', 'string', 'max:1000']]);
        $reviewToken = $fallback->review($event, $order, $refund, $request->user(), $data['late_refund_note'] ?? null);
        $refund->refresh()->load(['payer', 'items.item']);
        return response()->view('backend.clothing.refund-wallet-review', compact('event', 'order', 'refund', 'reviewToken'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function walletConfirm(Request $request, Event $event, ClothingOrder $order, ClothingRefund $refund, \App\Services\Clothing\ClothingRefundWalletFallbackService $fallback)
    {
        $request->headers->set('referer', route('backend.clothing.refunds.show', [$event, $order]));
        $data = $request->validate(['review_token' => ['required', 'string', 'max:30000'], 'confirmed' => ['required', 'accepted']]);
        $refund = $fallback->confirm($event, $order, $refund, $request->user(), $data['review_token']);
        return $this->completedResponse($request, $event, $order, $refund);
    }

    public function retry(Request $request, Event $event, ClothingOrder $order, ClothingRefund $refund, ClothingRefundService $refunds)
    {
        $refunds->authorizeOrder($event, $order, $request->user());
        abort_unless((int) $refund->clothing_order_id === (int) $order->id && (int) $refund->event_id === (int) $event->id, 404);
        $request->headers->set('referer', route('backend.clothing.refunds.show', [$event, $order]));
        $bankDetails = $this->bankDetails($request);
        $refund = app(\App\Services\Clothing\ClothingPayfastRefundService::class)->dispatch($refund, $request->user(), $bankDetails, $request->validate(['late_refund_note' => ['nullable', 'string', 'max:1000']])['late_refund_note'] ?? null);

        return $this->completedResponse($request, $event, $order, $refund);
    }

    public function deadline(Request $request, Event $event, \App\Models\TeamRegion $region)
    {
        abort_unless($request->user()->hasRole('super-user'), 403);
        $data = $request->validate(['clothing_deadline_at' => ['nullable', 'date_format:Y-m-d\\TH:i']]);
        \Illuminate\Support\Facades\DB::transaction(function () use ($event, $region, $data, $request) {
            $scope = \App\Models\EventRegion::where('event_id', $event->id)->where('region_id', $region->id)->lockForUpdate()->firstOrFail();
            $before = $scope->clothing_deadline_at;
            $scope->clothing_deadline_at = filled($data['clothing_deadline_at'] ?? null) ? \Carbon\CarbonImmutable::parse($data['clothing_deadline_at'], 'Africa/Johannesburg') : null;
            $scope->save();
            activity('refund')->performedOn($scope)->causedBy($request->user())->withProperties(['before' => $before?->toIso8601String(), 'after' => $scope->clothing_deadline_at?->toIso8601String()])->log('Clothing refund deadline updated');
        });
        return back()->with('success', 'Clothing deadline saved.');
    }

    public function reconcile(Request $request, Event $event, ClothingOrder $order, ClothingRefund $refund, ClothingRefundService $refunds)
    {
        abort_unless($request->user()->hasRole('super-user'), 403);
        $data = $request->validate(['reconciliation_note' => ['required', 'string', 'max:1000']]);
        $refunds->resolveReconciliation($event, $order, $refund, $request->user(), $data['reconciliation_note']);
        return back()->with('success', 'Clothing payout reconciliation recorded.');
    }

    public function complete(Request $request, Event $event, ClothingOrder $order, ClothingRefund $refund, ClothingRefundService $refunds)
    {
        app(ClothingRefundService::class)->authorizeOrder($event, $order, $request->user());
        $validated = $request->validate([
            'late_refund_note' => ['nullable', 'string', 'max:1000'],
            'reference' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:\/\-]{2,119}$/'],
            'paid_to_original_payer' => ['accepted'],
        ]);
        $refund = $refunds->completeExternal($event, $order, $refund, $request->user(), $validated['reference'], $validated['late_refund_note'] ?? null);

        return $this->completedResponse($request, $event, $order, $refund);
    }
}
