<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Native browser posts retain actionable errors; API responses keep their status. */
class CampActionFeedback
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $response = $next($request);
            if (($response->exception ?? null) instanceof HttpExceptionInterface) {
                throw $response->exception;
            }
            return $response;
        } catch (HttpExceptionInterface $error) {
            if (! in_array($error->getStatusCode(), [409, 422], true)
                || $request->isMethod('GET') || $request->expectsJson()
                || $request->header('Sec-Fetch-Mode') !== 'navigate') {
                throw $error;
            }
            $route = match (true) {
                $request->routeIs('backend.camps.*') => 'backend.camps.show',
                $request->routeIs('backend.practice-camps.*') => 'backend.practice-camps.show',
                $request->routeIs('camp.coach.*') => 'camp.coach.show',
                default => 'camp.invitation.show',
            };
            $parameter = $request->route('plan') ?? $request->route('token');
            if (! $parameter) throw $error;

            return redirect()->route($route, $parameter)->withErrors(['camp' => $error->getMessage() ?: 'This action could not be completed. Reload and review the current camp details.'])
                ->withInput($request->except(['_token', 'keys', 'selected', 'ids', 'identity_confirmed', 'confirmed', 'revision']));
        }
    }
}
