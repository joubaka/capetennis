<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Payments\Services\TeamLatePaymentReconciliationService;
use App\Http\Controllers\Controller;
use App\Models\{Event, TeamPaymentOrder, Transaction};
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Crypt, DB};
use Illuminate\Validation\ValidationException;

class TeamLatePaymentController extends Controller
{
    public function index(Request $request, Event $event)
    {
        $this->authorizeOperator($request);
        $orders = TeamPaymentOrder::where('event_id', $event->id)
            ->where('collection_status', 'reconciliation_required')->where('pay_status', false)
            ->with(['player:id,name,surname', 'team:id,name'])->orderBy('id')->paginate(20);

        return response()->view('backend.payfast-handoffs.late-team', compact('event', 'orders'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function preview(Request $request, Event $event, TeamPaymentOrder $order, TeamLatePaymentReconciliationService $service)
    {
        $this->authorizeOperator($request, $event, $order);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        // The operator cannot substitute a receipt or a financial amount.
        $receipts = Transaction::where('custom_str5', 'TeamOrder')->where('custom_int5', $order->id)->limit(2)->get();
        if ($receipts->count() !== 1) {
            throw ValidationException::withMessages(['payment' => 'One exact provider receipt is required. This payment needs separate review.']);
        }
        [$result, $fingerprint] = DB::transaction(function () use ($order, $receipts, $request, $data, $service): array {
            $result = $service->reconcile($order->id, (string) $receipts->sole()->pf_payment_id, $request->user(), $data['reason']);
            return [$result, $this->fingerprint($order)];
        });
        $token = Crypt::encryptString(json_encode([
            'actor' => $request->user()->id, 'event' => $event->id, 'order' => $order->id,
            'expires' => now()->addMinutes(10)->timestamp, 'reason' => $data['reason'], 'result' => $result,
            'fingerprint' => $fingerprint,
        ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        return response()->view('backend.payfast-handoffs.late-team-preview', compact('event', 'order', 'result', 'token', 'data'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function apply(Request $request, Event $event, TeamPaymentOrder $order, TeamLatePaymentReconciliationService $service)
    {
        try {
            return $this->applyReviewed($request, $event, $order, $service);
        } catch (ValidationException $exception) {
            return redirect()->route('backend.late-team-payments.index', $event)->withErrors($exception->errors());
        }
    }

    private function applyReviewed(Request $request, Event $event, TeamPaymentOrder $order, TeamLatePaymentReconciliationService $service)
    {
        $this->authorizeOperator($request, $event, $order);
        $data = $request->validate(['preview_token' => ['required', 'string', 'max:20000'], 'confirm' => ['required', 'accepted']]);
        try {
            $preview = json_decode(Crypt::decryptString($data['preview_token']), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $exception) {
            throw ValidationException::withMessages(['payment' => 'Invalid preview. Review the payment again.']);
        }
        if (($preview['actor'] ?? null) !== $request->user()->id || ($preview['event'] ?? null) !== $event->id
            || ($preview['order'] ?? null) !== $order->id || ($preview['expires'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages(['payment' => 'This preview expired or belongs to another review. Review the payment again.']);
        }
        DB::transaction(function () use ($request, $order, $service, $preview): void {
            $current = $service->reconcile($order->id, $preview['result']['pf_payment_id'], $request->user(), $preview['reason']);
            // Keep the canonical locks until apply; a newly created successor must be reviewed first.
            if (! $current['already_reconciled'] && ($current !== $preview['result'] || ! hash_equals($preview['fingerprint'], $this->fingerprint($order)))) {
                throw ValidationException::withMessages(['payment' => 'The checkout changed. Review the payment again.']);
            }
            $service->reconcile($order->id, $preview['result']['pf_payment_id'], $request->user(), $preview['reason'], true);
        });

        return redirect()->route('backend.late-team-payments.index', $event)
            ->with('success', "Order #{$order->id} reconciled and registration confirmed. No new charge or confirmation email was sent.");
    }

    private function authorizeOperator(Request $request, ?Event $event = null, ?TeamPaymentOrder $order = null): void
    {
        abort_unless($request->user()?->hasRole('super-user'), 403);
        if ($order) abort_unless((int) $order->event_id === (int) $event->id, 404);
    }

    private function fingerprint(TeamPaymentOrder $order): string
    {
        return hash('sha256', json_encode([
            'orders' => TeamPaymentOrder::where('event_id', $order->event_id)->forPlayerHistory([(int) $order->player_id])->orderBy('id')->get()->map->getAttributes()->all(),
            'receipts' => Transaction::where('custom_str5', 'TeamOrder')->where('custom_int5', $order->id)->orderBy('id')->get()->map->getAttributes()->all(),
            'audit' => \Spatie\Activitylog\Models\Activity::where('subject_type', $order->getMorphClass())->where('subject_id', $order->id)->orderBy('id')->get(['id', 'description', 'properties'])->toArray(),
            'team' => $order->team()->first()?->getAttributes(),
            'roster' => \App\Models\TeamPlayer::withoutGlobalScopes()->where('team_id', $order->team_id)->where('player_id', $order->player_id)->orderBy('id')->get()->map->getAttributes()->all(),
            'invitations' => \App\Models\TeamSelectionInvitation::where('event_id', $order->event_id)->where('player_id', $order->player_id)->orderBy('id')->get()->map->getAttributes()->all(),
        ], JSON_THROW_ON_ERROR));
    }
}
