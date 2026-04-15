<?php

namespace App\Http\Middleware;

use App\Models\Agreement;
use App\Models\PlayerAgreement;
use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;

class EnsureAgreementAccepted
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip entirely when Code of Conduct toggle is off
        if (SiteSetting::get('require_code_of_conduct', '0') !== '1') {
            return $next($request);
        }

        $user = auth()->user();

        if (!$user) {
            return $next($request);
        }

        $agreement = Agreement::where('is_active', 1)->latest()->first();

        if (!$agreement) {
            return $next($request);
        }

        // Check all players linked to this user
        $players = $user->players;

        if ($players->isEmpty()) {
            return $next($request);
        }

        foreach ($players as $player) {
            $accepted = PlayerAgreement::where('player_id', $player->id)
                ->where('agreement_id', $agreement->id)
                ->exists();

            if (!$accepted) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => 'You must accept the Code of Conduct before continuing.',
                        'redirect' => route('agreements.show'),
                    ], 403);
                }

                return redirect()->route('agreements.show')
                    ->with('error', 'You must accept the Code of Conduct before continuing.');
            }
        }

        return $next($request);
    }
}
