<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\PlayerPlusOrder;
use App\Services\Performance\{PlayerPlusEntitlement, PlayerPlusManagement, PlayerPlusService};
use Illuminate\Http\Request;

class PlayerPlusManagementController extends Controller
{
    public function index(Request $request, PlayerPlusEntitlement $access)
    {
        abort_unless(PlayerPlusManagement::ready(), 503, 'Player Plus management requires its local database migration.');
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:members,active,suspended,scheduled,expired,pending,problems,all']]);
        $search = trim($data['q'] ?? '');
        $status = $data['status'] ?? 'members';
        $players = Player::query()->with(['plusMembership.order', 'plusOverride'])->withCount('plusOrders');
        if ($search !== '') $players->where(fn ($q) => $q->where('id', ctype_digit($search) ? (int) $search : 0)
            ->orWhereRaw("CONCAT(name, ' ', surname) LIKE ?", ['%'.$search.'%'])
            ->orWhereHas('plusOrders', fn ($orders) => $orders->where('id', preg_match('/^P(\d+)$/i', $search, $match) ? (int) $match[1] : 0)
                ->orWhereHas('user', fn ($users) => $users->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))));
        match ($status) {
            'active' => $players->whereIn('id', $access->activePlayers()->select('players.id')),
            'suspended' => $players->whereHas('plusOverride', fn ($q) => $q->where('mode', 'suspended')),
            'scheduled' => $players->whereHas('plusOverride', fn ($q) => $q->where('mode', 'grant')->where('starts_at', '>', now())),
            'pending' => $players->whereHas('plusOrders', fn ($q) => $q->where('status', 'pending')),
            'problems' => $players->whereHas('plusOrders', fn ($q) => $q->whereNotNull('reconciliation_reason')),
            'expired' => $players->whereNotIn('id', $access->activePlayers()->select('players.id'))
                ->where(fn ($q) => $q->whereHas('plusMembership', fn ($m) => $m->where('ends_at', '<=', now()))
                    ->orWhereHas('plusOverride', fn ($m) => $m->where('mode', 'grant')->where('ends_at', '<=', now()))),
            'all' => null,
            default => $players->where(fn ($q) => $q->has('plusOrders')->orHas('plusOverride')->orHas('plusMembership')),
        };
        $players = $players->orderBy('surname')->orderBy('name')->orderBy('id')->paginate(20)->withQueryString();
        $activeIds = $access->activePlayers()->whereIn('id', $players->pluck('id'))->pluck('id')->all();
        $activeCount = $access->activePlayers()->count();
        return response()->view('backend.player-plus.manage-index', compact('players', 'search', 'status', 'activeIds', 'activeCount'))->header('Cache-Control', 'private, no-store');
    }

    public function show(Player $player, PlayerPlusManagement $management, PlayerPlusEntitlement $access)
    {
        abort_unless(PlayerPlusManagement::ready(), 503);
        $player->load(['plusMembership.order', 'plusOverride']);
        $active = $access->activePlayers()->whereKey($player->id)->exists();
        $orders = $player->plusOrders()->with(['user', 'subscription', 'subscriptionOrder.subscription'])->latest('id')->paginate(15);
        $history = $management->history($player)->paginate(10, ['*'], 'history_page');
        return response()->view('backend.player-plus.manage-show', compact('player', 'active', 'orders', 'history'))->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, Player $player, PlayerPlusManagement $management)
    {
        $management->change($player, $request->user(), $request->all());
        return redirect()->route('superadmin.player-plus.manage.show', $player)->with('success', 'Player Plus access updated.');
    }

    public function orderAction(Request $request, Player $player, PlayerPlusOrder $order, PlayerPlusService $service)
    {
        abort_unless((int) $order->player_id === (int) $player->id, 404);
        $data = $request->validate(['action' => ['required', 'in:close,stop-renewal'], 'reason' => ['required', 'string', 'min:10', 'max:1500'], 'confirm' => ['accepted']]);
        if ($data['action'] === 'close') $service->closeUnpaidAsAdmin($order, $request->user(), $data['reason']);
        else $service->cancelSubscription($order, $request->user(), $data['reason']);
        return redirect()->route('superadmin.player-plus.manage.show', $player)->with('success', $data['action'] === 'close' ? 'Unpaid checkout closed. Its payer can start a new purchase.' : 'PayFast confirmed automatic renewal stopped. Paid access is unchanged.');
    }
}
