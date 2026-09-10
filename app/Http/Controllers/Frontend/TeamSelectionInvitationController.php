<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\TeamSelectionInvitation;
use App\Models\ClothingOrder;
use App\Models\TeamSelectionRegionAnnouncement;
use App\Services\TeamSelection\TeamSelectionInvitationService;
use App\Services\Clothing\ClothingPriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TeamSelectionInvitationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $invitations = TeamSelectionInvitation::with(['selectionImport.event', 'region', 'team', 'player'])
            ->whereHas('player', fn ($query) => $query
                ->where('userId', $user->id)
                ->orWhereHas('users', fn ($users) => $users->whereKey($user->id)))
            ->whereIn('status', [
                TeamSelectionInvitation::INVITED,
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                TeamSelectionInvitation::PAID_CONFIRMED,
            ])->latest('invited_at')->get();

        return view('frontend.team-selection.index', compact('invitations'));
    }

    public function show(Request $request, TeamSelectionInvitation $invitation, TeamSelectionInvitationService $service)
    {
        $invitation->load(['selectionImport.event', 'region.clothingItems.sizes', 'team', 'player']);
        $service->authorizePlayer($invitation, $request->user());
        $canOrderClothing = $this->canOrderClothing($invitation);
        $clothingOrders = ClothingOrder::query()
            ->where('event_id', $invitation->event_id)
            ->where('team_id', $invitation->team_id)
            ->where('player_id', $invitation->player_id)
            ->where('user_id', $request->user()->id)
            ->latest()->get();
        $canSeeRegionAnnouncements = in_array($invitation->status, [
            TeamSelectionInvitation::INVITED,
            TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
            TeamSelectionInvitation::PAID_CONFIRMED,
        ], true);
        $regionAnnouncements = $canSeeRegionAnnouncements
            ? TeamSelectionRegionAnnouncement::query()
                ->where('event_id', $invitation->event_id)
                ->where('region_id', $invitation->region_id)
                ->latest()->get()
            : collect();

        return view('frontend.team-selection.show', compact('invitation', 'canOrderClothing', 'clothingOrders', 'regionAnnouncements'));
    }

    public function accept(Request $request, TeamSelectionInvitation $invitation, TeamSelectionInvitationService $service)
    {
        $accepted = $service->accept($invitation, $request->user());

        return redirect()->route('team.payment.payfast', [
            'team' => $accepted->team_id,
            'player' => $accepted->player_id,
            'event' => $accepted->event_id,
        ])->with('success', 'Continue with payment. Your team place is confirmed only after payment is verified.');
    }

    public function decline(Request $request, TeamSelectionInvitation $invitation, TeamSelectionInvitationService $service)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $replacement = $service->decline($invitation, $request->user(), $data['reason'] ?? null);

        return back()->with('success', $replacement
            ? 'Your unavailability was recorded and the next reserve has been invited.'
            : 'Your unavailability was recorded.');
    }

    public function clothing(Request $request, TeamSelectionInvitation $invitation, TeamSelectionInvitationService $service, ClothingPriceService $prices)
    {
        $invitation->load(['selectionImport.event', 'region.clothingItems.sizes', 'team', 'player']);
        $service->authorizePlayer($invitation, $request->user());
        abort_unless($invitation->status === TeamSelectionInvitation::PAID_CONFIRMED, 403, 'Complete event payment before ordering clothing.');
        abort_unless($this->canOrderClothing($invitation), 404);
        $items = $invitation->region->clothingItems
            ->filter(fn ($item) => (float) $item->price > 0 && $item->sizes->isNotEmpty())
            ->sortBy('ordering')->values();
        $requestToken = (string) Str::uuid();
        $payfastSettings = $prices->settings();

        return view('frontend.team-selection.clothing', compact('invitation', 'items', 'requestToken', 'payfastSettings'));
    }

    private function canOrderClothing(TeamSelectionInvitation $invitation): bool
    {
        $region = $invitation->region;

        return $invitation->status === TeamSelectionInvitation::PAID_CONFIRMED
            && (bool) $invitation->selectionImport?->include_clothing
            && $region
            && $region->usesOnlineClothingOrders()
            && (bool) $region->clothing_order
            && $region->clothingItems->contains(
                fn ($item) => (float) $item->price > 0 && $item->sizes->isNotEmpty()
            );
    }
}
