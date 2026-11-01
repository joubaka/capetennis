<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\PlayerPlusOrder;
use App\Services\Performance\{PlayerPlusReconciliation, PlayerPlusService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlayerPlusReviewController extends Controller
{
    public function index(Request $request, PlayerPlusReconciliation $review)
    {
        abort_unless($request->user()->hasRole('super-user'), 403);
        abort_unless(PlayerPlusService::ready(), 404);
        $orders = PlayerPlusOrder::whereNotNull('reconciliation_reason')->with(['user', 'player'])->latest('updated_at')->paginate(20);
        return response()->view('backend.player-plus.index', compact('orders', 'review'))->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, PlayerPlusOrder $order, PlayerPlusReconciliation $review)
    {
        abort_unless($request->user()->hasRole('super-user'), 403);
        return DB::transaction(function () use ($order, $review) {
            // Verified callbacks lock this order before recording receipts. Keep the displayed
            // receipt set and its confirmation fingerprint in the same serialized snapshot.
            $order = PlayerPlusOrder::lockForUpdate()->findOrFail($order->id);
            abort_unless(filled($order->reconciliation_reason), 404);
            $order->load(['user', 'player']);
            $receipts = $review->receipts($order)->paginate(20);
            $fingerprint = $review->fingerprint($order);
            $resolution = $review->reviews($order)->where('metadata->payment_fingerprint', $fingerprint)->first();
            $history = $review->reviews($order)->paginate(10, ['*'], 'history_page');
            return response()->view('backend.player-plus.show', compact('order', 'receipts', 'fingerprint', 'resolution', 'history'))->header('Cache-Control', 'private, no-store');
        }, 3);
    }

    public function store(Request $request, PlayerPlusOrder $order, PlayerPlusReconciliation $review)
    {
        abort_unless($request->user()->hasRole('super-user'), 403);
        $data = $request->validate(['payment_fingerprint' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
            'reference' => ['required', 'string', 'min:3', 'max:150'], 'note' => ['required', 'string', 'min:10', 'max:1500'],
            'confirm' => ['accepted']]);
        $review->review($order, $request->user(), $data['payment_fingerprint'], $data['reference'], $data['note']);
        return redirect()->route('superadmin.player-plus.show', $order)->with('success', 'External resolution evidence recorded. Payment and membership records are unchanged.');
    }
}
