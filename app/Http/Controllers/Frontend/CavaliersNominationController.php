<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\{CategoryEvent, Event};
use App\Services\CavaliersNominationDecline;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CavaliersNominationController extends Controller
{
    public function decline(Request $request, Event $event, CategoryEvent $categoryEvent, int $nomination, CavaliersNominationDecline $decisions)
    {
        try { $review = $decisions->preview($request->user(), $event, $categoryEvent, $nomination); }
        catch (ValidationException $exception) {
            if ($request->expectsJson()) throw $exception;
            return redirect()->route('events.show', $event)->withErrors($exception->errors());
        }
        $token = (string) Str::uuid();
        $request->session()->put('cavaliers_decline', [$token => $review]);
        return response()->view('frontend.event.cavaliers-decline', compact('event', 'categoryEvent', 'review', 'token'))->header('Cache-Control', 'private, no-store');
    }

    public function confirm(Request $request, Event $event, CategoryEvent $categoryEvent, int $nomination, CavaliersNominationDecline $decisions)
    {
        $input = $request->validate(['token' => 'required|uuid', 'confirmed' => 'accepted']);
        $review = $request->session()->get('cavaliers_decline.'.$input['token']);
        abort_unless($review && (int) $review['nomination_id'] === $nomination, 409, 'Open and review this nomination before confirming.');
        try { $changed = $decisions->confirm($request->user(), $event, $categoryEvent, $review); }
        catch (ValidationException $exception) {
            if ($request->expectsJson()) throw $exception;
            return redirect()->route('events.show', $event)->withErrors($exception->errors());
        }
        return redirect()->route('events.show', $event)->with('success', $changed ? 'Nomination declined. The event manager’s declined list has been updated.' : 'This nomination was already declined.');
    }
}
