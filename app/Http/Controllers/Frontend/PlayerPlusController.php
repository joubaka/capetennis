<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\{Player, PlayerPlusMembership, PlayerPlusOrder, SiteSetting};
use App\Services\Performance\{PlayerPlusEntitlement, PlayerPlusService, PlayerRatingAccess};
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlayerPlusController extends Controller
{
    public function index(Request $request, PlayerPlusEntitlement $entitlements)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'min:3', 'max:100']]);
        $search = trim($filters['search'] ?? '');
        $players = $search === '' ? $entitlements->linkedPlayers($request->user()) : Player::query()->where(function ($query) use ($search) {
            $query->where('name', 'like', '%'.$search.'%')->orWhere('surname', 'like', '%'.$search.'%');
        });
        $players = $players->orderBy('surname')->orderBy('name')->paginate(20)->withQueryString();
        $enabled = SiteSetting::get(PlayerRatingAccess::SETTING, '0') === '1';
        return response()->view('frontend.player-plus.index', compact('players', 'search', 'enabled'))->header('Cache-Control', 'private, no-store');
    }

    public function player(Request $request, Player $player, PlayerPlusEntitlement $entitlements)
    {
        $membership = PlayerPlusMembership::where('player_id', $player->id)->first();
        $canViewRating = app(PlayerRatingAccess::class)->allows($request->user(), $player);
        $linked = $entitlements->linkedPlayers($request->user())->whereKey($player->id)->exists();
        $enabled = SiteSetting::get(PlayerRatingAccess::SETTING, '0') === '1';
        return response()->view('frontend.player-plus.player', [
            'player' => $player, 'membership' => $membership, 'canViewRating' => $canViewRating,
            'linked' => $linked, 'enabled' => $enabled, 'token' => (string) Str::uuid(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, Player $player, PlayerPlusService $service)
    {
        $data = $request->validate(['request_token' => ['required', 'uuid'], 'confirm' => ['accepted']]);
        $order = $service->start($player, $request->user(), $data['request_token']);
        return redirect()->route('player-plus.orders.show', $order);
    }

    public function show(Request $request, PlayerPlusOrder $order)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403);
        $order->load('player');
        $form = null;
        return response()->view('frontend.player-plus.order', compact('order', 'form'))->header('Cache-Control', 'private, no-store');
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
}
