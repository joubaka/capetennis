<?php

namespace App\Http\Controllers;

use App\Domain\Entries\Services\{EntryEligibilityService, PrivateRegistrationLinkService};
use App\Models\{CategoryEvent, Event, Player};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PrivateRegistrationLinkController extends Controller
{
    public function create(Request $request, Event $event, CategoryEvent $categoryEvent, PrivateRegistrationLinkService $service)
    {
        $service->authorize($event, $categoryEvent, $request->user());
        $service->assertCategory($categoryEvent);
        return view('backend.event.individual.private-registration-link', compact('event', 'categoryEvent'));
    }

    public function store(Request $request, Event $event, CategoryEvent $categoryEvent, PrivateRegistrationLinkService $service)
    {
        $service->authorize($event, $categoryEvent, $request->user());
        $data = $request->validate(['player_id' => 'required|integer|exists:players,id', 'reviewed_email' => 'required|email|max:255', 'reviewed_contacts' => 'required|string|size:64']);
        try {
            $issued = $service->issue($event, $categoryEvent, Player::findOrFail($data['player_id']), $request->user(), $data['reviewed_email'], $data['reviewed_contacts']);
        } catch (\RuntimeException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['player_id' => $e->getMessage()]);
        }
        return response()->view('backend.event.individual.private-registration-link', compact('event', 'categoryEvent', 'issued'))->header('Cache-Control', 'private, no-store');
    }

    public function recipient(Request $request, Event $event, CategoryEvent $categoryEvent, Player $player, PrivateRegistrationLinkService $service)
    {
        $service->authorize($event, $categoryEvent, $request->user());
        $service->assertCategory($categoryEvent);
        $linked = \Illuminate\Support\Facades\DB::table('user_players')->where('player_id', $player->id)->exists();
        $contacts = app(\App\Services\PrivateRegistrationInvitationService::class)->contacts($player);
        $email = $contacts['primary'];
        return response()->json(['player_id' => $player->id, 'email' => $email, 'copies' => $contacts['copies'], 'reviewed_contacts' => $contacts['fingerprint'], 'linked' => $linked,
            'message' => !$linked ? 'This player has no linked account. Link the player to their existing account first.'
                : (!$email ? 'Add a valid primary email to this player before enabling registration.' : null)]);
    }

    public function show(Request $request, int $link, PrivateRegistrationLinkService $service)
    {
        try {
            $grant = $service->resolveById($link);
        } catch (ValidationException $e) {
            return $this->unavailable(collect($e->errors())->flatten()->first());
        }
        return $this->landing($request, $grant, $service);
    }

    private function landing(Request $request, \App\Models\PrivateRegistrationLink $link, PrivateRegistrationLinkService $service)
    {
        $service->assertLinkedAccount($link, $request->user());
        if ($response = $service->resume($link, $request->user())) {
            return $response;
        }
        try {
            app(EntryEligibilityService::class)->assertCanRegister($link->categoryEvent, $link->player_id);
        } catch (\RuntimeException $e) {
            return $this->unavailable($e->getMessage());
        }
        return response()->view('frontend.event.private-registration', ['link' => $link, 'event' => $link->categoryEvent->event])
            ->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'private, no-store');
    }

    private function unavailable(string $message)
    {
        // A GET validation redirect can loop back onto the same expired link.
        return response()->view('frontend.event.private-registration-unavailable', compact('message'), 410)
            ->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'private, no-store');
    }
}
