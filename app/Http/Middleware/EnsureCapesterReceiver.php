<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class EnsureCapesterReceiver
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('capester.enabled'), 404);
        abort_if(app()->environment('production') && ! $request->secure(), 426);
        $token = $request->user()?->currentAccessToken();
        $bearer = $request->bearerToken();
        abort_unless($bearer && $token instanceof PersonalAccessToken
            && in_array('capester:receive', $token->abilities ?? [], true)
            && (string) PersonalAccessToken::findToken($bearer)?->getKey() === (string) $token->getKey()
            && $request->user()->hasRole('super-user'), 403);
        return $next($request);
    }
}
