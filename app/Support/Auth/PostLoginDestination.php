<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Throwable;

class PostLoginDestination
{
    private const EXPLICIT_SESSION_KEY = 'auth.post_login_redirect';

    public function captureExplicit(Request $request): void
    {
        if (! $request->has('redirect')) {
            return;
        }

        $redirect = (string) $request->input('redirect', '');

        if ($this->isSafeLocalPath($redirect)) {
            $request->session()->put(self::EXPLICIT_SESSION_KEY, $redirect);
        } else {
            $request->session()->forget(self::EXPLICIT_SESSION_KEY);
        }
    }

    public function resolve(Request $request, ?User $user = null): string
    {
        $explicit = $request->filled('redirect')
            ? (string) $request->input('redirect')
            : (string) $request->session()->pull(self::EXPLICIT_SESSION_KEY, '');

        // Never leave an old explicit or intended destination behind after a
        // completed login decision, regardless of which destination wins.
        $request->session()->forget(self::EXPLICIT_SESSION_KEY);
        $intended = (string) $request->session()->pull('url.intended', '');

        if ($this->isAllowedDestination($explicit)) {
            return $explicit;
        }

        if ($this->isAllowedDestination($intended)) {
            return $intended;
        }

        $user ??= $request->user();

        if ($user && ($user->hasRole('super-user') || $user->adminEvents()->exists())) {
            return route('backend.dashboard');
        }

        return '/';
    }

    public function isSafeLocalPath(?string $path): bool
    {
        if (! is_string($path) || $path === '' || ! str_starts_with($path, '/')) {
            return false;
        }

        if (preg_match('/%(?:2f|5c|0d|0a|00)/i', $path)) {
            return false;
        }

        $decoded = $path;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $next = rawurldecode($decoded);
            if ($next === $decoded) {
                break;
            }
            $decoded = $next;
        }

        if (! str_starts_with($decoded, '/')
            || str_starts_with($decoded, '//')
            || str_contains($decoded, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $decoded)) {
            return false;
        }

        $parts = parse_url($decoded);

        return $parts !== false
            && ! isset($parts['scheme'])
            && ! isset($parts['host'])
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }

    public function isAllowedDestination(?string $path): bool
    {
        if (! $this->isSafeLocalPath($path)) {
            return false;
        }

        try {
            $route = Route::getRoutes()->match(Request::create($path, 'GET'));
        } catch (Throwable) {
            return false;
        }

        return in_array($route->getName(), [
            'home',
            'events.index',
            'events.show',
            'events.results',
            'register.register',
            'frontend.fixtures.index',
            'frontend.fixtures.show',
            'frontend.fixtures.indexRound',
            'frontend.bracket.fixtures',
            'frontend.fixtures.draw',
            'frontend.fixtures.enter-scores',
            'frontend.fixtures.venue',
            'frontend.fixtures.enter-scores.venue',
            'frontend.scoring.workspace',
            'backend.dashboard',
            'player.profiles.pending',
            'player.profile.create',
            'player.profile.edit',
            'player.profile.status',
            'agreements.show',
            'frontend.player.profile',
        ], true);
    }
}
