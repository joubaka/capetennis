<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureJtaIntegrationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production')
            && config('integrations.jta.require_https', true)
            && ! $request->secure()) {
            return new JsonResponse(['message' => 'HTTPS is required.'], 426);
        }

        $token = $request->user()?->currentAccessToken();
        $ability = (string) config('integrations.jta.ability', 'jta-results:read');

        if (! $request->bearerToken() || ! $token || ! $request->user()->tokenCan($ability)) {
            return new JsonResponse(['message' => 'This token is not authorized for JTA result access.'], 403);
        }

        return $next($request);
    }
}
