<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePlayerProfileUpdated
{
    /**
     * Routes that should be excluded from profile update check
     */
    protected array $excludedRoutes = [
        'player.profile.update',
        'player.profile.edit',
        'player.profile.confirm',
        'player.profile.remove',
        'player.profiles.pending',
        'logout',
        'profile.*',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (!$user) {
            return $next($request);
        }

        // Skip check for excluded routes
        $currentRoute = $request->route()?->getName();
        foreach ($this->excludedRoutes as $pattern) {
            if ($currentRoute && (
                $currentRoute === $pattern ||
                (str_contains($pattern, '*') && str_starts_with($currentRoute, rtrim($pattern, '*')))
            )) {
                return $next($request);
            }
        }

        // Check all players linked to this user
        $players = $user->players;

        if ($players->isEmpty()) {
            return $next($request);
        }

        // Find all players needing update
        $playersNeedingUpdate = $players->filter(function ($player) {
            return $player->needsProfileUpdate() || !$player->isProfileComplete();
        });

        if ($playersNeedingUpdate->isNotEmpty()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Player profiles need to be updated before continuing.',
                    'redirect' => route('player.profiles.pending'),
                    'players' => $playersNeedingUpdate->pluck('id'),
                ], 403);
            }

            // Store intended URL
            session()->put('url.intended', $request->fullUrl());

            return redirect()->route('player.profiles.pending')
                ->with('warning', 'Please review and update your player profiles before continuing.');
        }

        return $next($request);
    }
}
