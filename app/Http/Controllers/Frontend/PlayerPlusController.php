<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\{Player, PlayerPlusMembership, PlayerPlusOrder};
use App\Services\Performance\{PlayerPlusEntitlement, PlayerPlusReadiness, PlayerPlusService, PlayerRatingAccess};
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlayerPlusController extends Controller
{
    public const TERMS_VERSION = '2026-11-03-wallet';

    public function terms(Request $request)
    {
        return response()->view('frontend.player-plus.terms', ['version' => self::TERMS_VERSION,
            'workspace' => $request->user() ? app(\App\Services\PlayerWorkspaceContext::class)->fromRequest($request->user(), $request->integer('player') ?: null) : null])->header('Cache-Control', 'private, no-store');
    }

    public function orders(Request $request)
    {
        $orders = PlayerPlusService::ready()
            ? PlayerPlusOrder::where('user_id', $request->user()->id)->with('player')->latest('id')->paginate(20)->withQueryString()
            : new LengthAwarePaginator([], 0, 20, 1, ['path' => route('player-plus.orders.index')]);
        return response()->view('frontend.player-plus.orders', compact('orders') + ['workspace' => app(\App\Services\PlayerWorkspaceContext::class)->fromRequest($request->user(), $request->integer('player') ?: null)])->header('Cache-Control', 'private, no-store');
    }
    public function index(Request $request, PlayerPlusEntitlement $entitlements)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'min:3', 'max:100']]);
        $search = trim($filters['search'] ?? '');
        $players = $search === '' ? $entitlements->linkedPlayers($request->user()) : Player::query()->where(function ($query) use ($search) {
            $query->where('name', 'like', '%'.$search.'%')->orWhere('surname', 'like', '%'.$search.'%');
        });
        $players = $players->orderBy('surname')->orderBy('name')->paginate(20)->withQueryString();
        $enabled = PlayerPlusService::salesEnabled();
        return response()->view('frontend.player-plus.index', compact('players', 'search', 'enabled') + ['workspace' => app(\App\Services\PlayerWorkspaceContext::class)->fromRequest($request->user(), $request->integer('player') ?: null)])->header('Cache-Control', 'private, no-store');
    }

    public function player(Request $request, Player $player, PlayerPlusEntitlement $entitlements)
    {
        $linked = $entitlements->linkedPlayers($request->user())->whereKey($player->id)->exists();
        $canInspectMembership = $linked || $request->user()->hasRole('super-user');
        $membershipQuery = PlayerPlusService::ready() ? PlayerPlusMembership::where('player_id', $player->id)
            ->whereHas('order', fn ($query) => $query->verifiedSettlement()
                ->whereColumn('player_plus_orders.player_id', 'player_plus_memberships.player_id')) : null;
        $membership = $canInspectMembership ? $membershipQuery?->first() : null;
        $managementReady = \App\Services\Performance\PlayerPlusManagement::ready();
        $adminAccess = $canInspectMembership && $managementReady ? $player->plusOverride()->first() : null;
        // Sponsors can learn whether another purchase is possible, never private
        // expiry, billing, suspension or staff-grant details from this offer page.
        $purchaseBlocked = !$canInspectMembership && (($membershipQuery && (clone $membershipQuery)->where('starts_at', '<=', now())->where('ends_at', '>', now())->exists())
            || ($managementReady && $player->plusOverride()->where(fn ($q) => $q->where('mode', 'suspended')->orWhere(fn ($grant) => $grant->where('mode', 'grant')->where('starts_at', '<=', now())->where('ends_at', '>', now())))->exists()));
        $canViewRating = app(PlayerRatingAccess::class)->allows($request->user(), $player);
        $enabled = PlayerPlusService::salesEnabled();
        $workspaceHistory = $canViewRating ? app(\App\Services\Performance\PlayerRatingHistoryService::class)->forPlayer($player) : null;
        $readiness = app(PlayerPlusReadiness::class)->forPlayer($request->user(), $player, $workspaceHistory);
        return response()->view('frontend.player-plus.player', [
            'workspace' => app(\App\Services\PlayerWorkspaceContext::class)->forPlayer($request->user(), $player, $workspaceHistory),
            'player' => $player, 'membership' => $membership, 'canViewRating' => $canViewRating,
            'linked' => $linked, 'enabled' => $enabled, 'token' => (string) Str::uuid(),
            'adminAccess' => $adminAccess, 'purchaseBlocked' => $purchaseBlocked,
            'readiness' => $readiness, 'termsVersion' => self::TERMS_VERSION, 'priceCents' => PlayerPlusService::priceCents(),
            'monthlyEnabled' => PlayerPlusService::monthlyEnabled(), 'monthlyPriceCents' => PlayerPlusService::monthlyPriceCents(),
            'walletAvailableCents' => app(PlayerPlusService::class)->walletAvailableCents($request->user()),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, Player $player, PlayerPlusService $service)
    {
        if ($request->input('payment_method') === 'wallet') {
            $data = $request->validate(['request_token' => ['required', 'uuid'], 'confirm' => ['accepted'],
                'wallet_consent' => ['required', 'accepted'], 'recurring_consent' => ['prohibited'],
                'plan' => ['required', 'in:annual'], 'payment_method' => ['required', 'in:wallet'],
                'terms_version' => ['required', \Illuminate\Validation\Rule::in([self::TERMS_VERSION])],
                'quoted_price_cents' => ['required', 'integer', 'min:'.PlayerPlusService::MIN_PRICE_CENTS, 'max:'.PlayerPlusService::MAX_PRICE_CENTS]]);
            $order = $service->purchaseWithWallet($player, $request->user(), $data['request_token'], (int) $data['quoted_price_cents'], $data['terms_version']);
            return redirect()->route('player-plus.orders.show', $order)->with('success', 'Wallet payment confirmed. Player Plus is active for the purchased 12-month term, with no automatic renewal.');
        }
        $data = $request->validate(['request_token' => ['required', 'uuid'], 'confirm' => ['accepted'],
            'payment_method' => ['sometimes', 'in:payfast'],
            'plan' => ['required', \Illuminate\Validation\Rule::in(['annual_recurring', 'monthly'])],
            'recurring_consent' => ['required', 'accepted'],
            'terms_version' => ['required', \Illuminate\Validation\Rule::in([self::TERMS_VERSION])],
            'quoted_price_cents' => ['required', 'integer', 'min:'.PlayerPlusService::MIN_PRICE_CENTS, 'max:'.PlayerPlusService::MAX_PRICE_CENTS]],
            ['quoted_price_cents.min' => 'Please reload the player membership page to review a supported annual price.']);
        [$order, $handoff] = DB::transaction(function () use ($service, $player, $request, $data) {
            $order = $service->start($player, $request->user(), $data['request_token'], (int) $data['quoted_price_cents'],
                $data['plan'], ! empty($data['recurring_consent']));
            // start() may reuse a payer's immutable older checkout. New consent
            // must never be represented as acceptance of a different saved price.
            $matches = $order->price_cents === (int) $data['quoted_price_cents']
                && $order->plan === $data['plan'] && $order->recurring_consented_at !== null;
            $canBegin = $order->status === 'pending' && $order->expires_at->isFuture() && !$order->payfast_handed_off_at;
            $audit = \App\Models\AuditEvent::where('action', 'player-plus.purchase-details-accepted')->where('subject_type', PlayerPlusOrder::class)
                ->where('subject_id', (string) $order->id)->first();
            if ($matches && $canBegin && !$audit) {
                $audit = app(AuditWriter::class)->record(['category' => 'business', 'action' => 'player-plus.purchase-details-accepted',
                    'actor' => $request->user(), 'subject' => $order,
                    'metadata' => ['terms_version' => self::TERMS_VERSION, 'price_cents' => $order->price_cents,
                        'plan' => $order->plan ?? 'annual', 'recurring_consent' => (bool) $order->recurring_consented_at]], true);
                if (! $audit) throw new \RuntimeException('Purchase details could not be recorded. Please try again.');
                $audit = \App\Models\AuditEvent::findOrFail($audit);
            }
            $accepted = $matches && $audit && (int) $audit->actor_id === (int) $request->user()->id
                && ($audit->metadata['terms_version'] ?? null) === self::TERMS_VERSION
                && ($audit->metadata['price_cents'] ?? null) === $order->price_cents
                && ($audit->metadata['plan'] ?? null) === $order->plan
                && ($audit->metadata['recurring_consent'] ?? false) === true;
            $handoff = $accepted && $canBegin;
            if ($handoff) $order = $service->handoff($order, $request->user());
            return [$order, $handoff];
        }, 3);
        if ($handoff) return $this->launch($order, $service);
        return redirect()->route('player-plus.orders.show', $order)->with('plus_checkout_review',
            'Your saved checkout is shown below. Review its agreed price, plan and payment status before continuing.');
    }

    public function show(Request $request, PlayerPlusOrder $order)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403);
        $order->load('player');
        $subscription = PlayerPlusService::monthlyReady() ? ($order->subscription_order_id ? $order->subscriptionOrder?->subscription : $order->subscription) : null;
        $retrySalesOpen = PlayerPlusService::salesEnabled() && (!$order->isMonthly() || PlayerPlusService::monthlyEnabled());
        $expired = !$order->expires_at->isFuture();
        $unpaid = !$order->pay_status && !$order->payfast_paid && !$order->wallet_debited
            && !$order->activated_at && !$order->term_starts_at && !$order->term_ends_at
            && !filled($order->reconciliation_reason) && !filled($order->payfast_pf_payment_id);
        $canClose = $unpaid && $order->status === 'pending' && ($expired || !$order->payfast_handed_off_at);
        $canRestart = $unpaid && $order->player && $retrySalesOpen
            && in_array($order->status, ['pending', 'cancelled', 'expired'], true)
            && ($expired || ($order->status === 'cancelled' && !$order->payfast_handed_off_at));
        if (($canClose || $canRestart) && app(\App\Services\Performance\PlayerPlusReconciliation::class)->receipts($order)->exists()) {
            $canClose = $canRestart = false;
        }
        $returnedFromCancel = $request->query('checkout') === 'cancelled';
        $statusUrl = route('player-plus.orders.show', $returnedFromCancel ? ['order' => $order, 'checkout' => 'cancelled'] : $order);
        $form = null;
        return response()->view('frontend.player-plus.order', compact('order', 'form', 'subscription', 'canClose', 'canRestart', 'returnedFromCancel', 'retrySalesOpen', 'statusUrl') + ['workspace' => app(\App\Services\PlayerWorkspaceContext::class)->forPlayer($request->user(), $order->player)])->header('Cache-Control', 'private, no-store');
    }

    public function pay(Request $request, PlayerPlusOrder $order, PlayerPlusService $service)
    {
        $order = $service->handoff($order, $request->user());
        return $this->launch($order, $service);
    }

    private function launch(PlayerPlusOrder $order, PlayerPlusService $service)
    {
        $form = $service->form($order);
        return response()->view('frontend.player-plus.launch', compact('order', 'form'))->header('Cache-Control', 'private, no-store');
    }

    public function paymentReturn(Request $request, PlayerPlusOrder $order)
    {
        $status = $this->returnStatus($request, $order);
        if ($status['state'] !== 'pending') return redirect()->to($status['destination'])->header('Cache-Control', 'private, no-store');
        $order->load('player');
        return response()->view('frontend.player-plus.order', ['order' => $order, 'form' => null, 'returnFlow' => true,
            'workspace' => app(\App\Services\PlayerWorkspaceContext::class)->forPlayer($request->user(), $order->player)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function paymentStatus(Request $request, PlayerPlusOrder $order)
    {
        return response()->json($this->returnStatus($request, $order))->header('Cache-Control', 'private, no-store');
    }

    private function returnStatus(Request $request, PlayerPlusOrder $order): array
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403);
        $receipt = route('player-plus.orders.show', $order);
        if ($order->status === 'pending' && $order->payfast_handed_off_at && $order->expires_at->isFuture()
            && !$order->pay_status && !$order->payfast_paid && !$order->activated_at && !$order->reconciliation_reason) {
            return ['state' => 'pending', 'destination' => null];
        }
        $verified = $order->hasVerifiedSettlement()
            && !$order->reconciliation_reason && $order->term_starts_at?->lte(now()) && $order->term_ends_at?->isFuture();
        $player = $order->player;
        $canView = $verified && $player && app(PlayerPlusEntitlement::class)->forPlayer($request->user(), $player)
            && app(PlayerRatingAccess::class)->allows($request->user(), $player);
        return ['state' => $canView ? 'verified' : 'receipt',
            'destination' => $canView ? route('my.tennis', ['player' => $player->id]).'#rating' : $receipt];
    }

    public function cancel(Request $request, PlayerPlusOrder $order, PlayerPlusService $service)
    {
        $service->cancel($order, $request->user());
        return redirect()->route('player-plus.orders.show', $order);
    }

    public function cancelSubscription(Request $request, PlayerPlusOrder $order, PlayerPlusService $service)
    {
        abort_unless(PlayerPlusService::monthlyReady(), 404);
        $service->cancelSubscription($order, $request->user());
        return redirect()->route('player-plus.orders.show', $order)->with('success', 'PayFast confirmed automatic renewal is stopped. Your verified paid access is unchanged.');
    }
}
