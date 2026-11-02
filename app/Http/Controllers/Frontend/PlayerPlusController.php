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
    public const TERMS_VERSION = '2026-11-01-recurring';

    public function terms()
    {
        return response()->view('frontend.player-plus.terms', ['version' => self::TERMS_VERSION])->header('Cache-Control', 'private, no-store');
    }

    public function orders(Request $request)
    {
        $orders = PlayerPlusService::ready()
            ? PlayerPlusOrder::where('user_id', $request->user()->id)->with('player')->latest('id')->paginate(20)
            : new LengthAwarePaginator([], 0, 20, 1, ['path' => route('player-plus.orders.index')]);
        return response()->view('frontend.player-plus.orders', compact('orders'))->header('Cache-Control', 'private, no-store');
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
        return response()->view('frontend.player-plus.index', compact('players', 'search', 'enabled'))->header('Cache-Control', 'private, no-store');
    }

    public function player(Request $request, Player $player, PlayerPlusEntitlement $entitlements)
    {
        $membership = PlayerPlusService::ready() ? PlayerPlusMembership::where('player_id', $player->id)
            ->whereHas('order', fn ($query) => $query->where('status', 'completed')->where('pay_status', true)->where('payfast_paid', true)
                ->whereNotNull('activated_at')->whereColumn('player_plus_orders.player_id', 'player_plus_memberships.player_id'))->first() : null;
        $canViewRating = app(PlayerRatingAccess::class)->allows($request->user(), $player);
        $linked = $entitlements->linkedPlayers($request->user())->whereKey($player->id)->exists();
        $enabled = PlayerPlusService::salesEnabled();
        $readiness = app(PlayerPlusReadiness::class)->forPlayer($request->user(), $player);
        return response()->view('frontend.player-plus.player', [
            'player' => $player, 'membership' => $membership, 'canViewRating' => $canViewRating,
            'linked' => $linked, 'enabled' => $enabled, 'token' => (string) Str::uuid(),
            'readiness' => $readiness, 'termsVersion' => self::TERMS_VERSION, 'priceCents' => PlayerPlusService::priceCents(),
            'monthlyEnabled' => PlayerPlusService::monthlyEnabled(), 'monthlyPriceCents' => PlayerPlusService::monthlyPriceCents(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, Player $player, PlayerPlusService $service)
    {
        $data = $request->validate(['request_token' => ['required', 'uuid'], 'confirm' => ['accepted'],
            'plan' => ['required', \Illuminate\Validation\Rule::in(['annual_recurring', 'monthly'])],
            'recurring_consent' => ['required', 'accepted'],
            'terms_version' => ['required', \Illuminate\Validation\Rule::in([self::TERMS_VERSION])],
            'quoted_price_cents' => ['required', 'integer', 'min:'.PlayerPlusService::MIN_PRICE_CENTS, 'max:'.PlayerPlusService::MAX_PRICE_CENTS]],
            ['quoted_price_cents.min' => 'Please reload the player membership page to review a supported annual price.']);
        $order = DB::transaction(function () use ($service, $player, $request, $data) {
            $order = $service->start($player, $request->user(), $data['request_token'], (int) $data['quoted_price_cents'],
                $data['plan'], ! empty($data['recurring_consent']));
            if (! \App\Models\AuditEvent::where('action', 'player-plus.purchase-details-accepted')->where('subject_type', PlayerPlusOrder::class)
                ->where('subject_id', (string) $order->id)->exists()) {
                $audit = app(AuditWriter::class)->record(['category' => 'business', 'action' => 'player-plus.purchase-details-accepted',
                    'actor' => $request->user(), 'subject' => $order,
                    'metadata' => ['terms_version' => self::TERMS_VERSION, 'price_cents' => $order->price_cents,
                        'plan' => $order->plan ?? 'annual', 'recurring_consent' => (bool) $order->recurring_consented_at]], true);
                if (! $audit) throw new \RuntimeException('Purchase details could not be recorded. Please try again.');
            }
            return $order;
        }, 3);
        return redirect()->route('player-plus.orders.show', $order);
    }

    public function show(Request $request, PlayerPlusOrder $order)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403);
        $order->load('player');
        $subscription = PlayerPlusService::monthlyReady() ? ($order->subscription_order_id ? $order->subscriptionOrder?->subscription : $order->subscription) : null;
        $form = null;
        return response()->view('frontend.player-plus.order', compact('order', 'form', 'subscription'))->header('Cache-Control', 'private, no-store');
    }

    public function pay(Request $request, PlayerPlusOrder $order, PlayerPlusService $service)
    {
        $order = $service->handoff($order, $request->user());
        $form = $service->form($order);
        return response()->view('frontend.player-plus.order', compact('order', 'form'))->header('Cache-Control', 'private, no-store');
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
