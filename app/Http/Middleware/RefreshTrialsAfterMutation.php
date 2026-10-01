<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\InterprovincialTrials\TrialRefreshQueue;

class RefreshTrialsAfterMutation
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (! $request->isMethodSafe()) {
            app(TrialRefreshQueue::class)->flush();
        }
        return $response;
    }
}
