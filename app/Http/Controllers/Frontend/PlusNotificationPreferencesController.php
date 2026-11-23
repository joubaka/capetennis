<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\PlusDigestPreferences;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlusNotificationPreferencesController extends Controller
{
    public function show(Request $request, PlusDigestPreferences $preferences)
    {
        return view('frontend.player-plus.notifications', [
            'preferences' => $preferences->forUser($request->user()), 'ready' => $preferences->ready(),
        ]);
    }

    public function update(Request $request, PlusDigestPreferences $preferences)
    {
        $data = $request->validate(['choice' => ['required', Rule::in(['daily', 'off', 'snooze7', 'snooze30'])]]);
        $preferences->update($request->user(), $data['choice']);
        return redirect()->route('player-plus.notifications')->with('status', 'Your Plus update email preference has been saved.');
    }
}
