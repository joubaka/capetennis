<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDisciplinarySystemEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (SiteSetting::disciplinarySystemEnabled()) {
            return $next($request);
        }

        $message = 'The disciplinary case system is currently disabled by the Super Admin. Existing records remain available for audit.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 503);
        }

        return redirect()->back()->withErrors($message);
    }
}
