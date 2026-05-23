<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Services\PlatformHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * PlatformHealthController
 *
 * Serves the operational health dashboard and a JSON health API.
 * Restricted to super-user only.
 */
class PlatformHealthController extends Controller
{
    public function __construct(private PlatformHealthService $health) {}

    /**
     * Full HTML health dashboard.
     */
    public function index(Request $request)
    {
        $ttl  = 30; // seconds
        $data = Cache::remember('platform.health.dashboard', $ttl, fn () => $this->health->all());

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return view('backend.platform.health', $data);
    }

    /**
     * Lightweight JSON endpoint for uptime monitors / CI.
     * Returns HTTP 200 if all ok/warn, HTTP 503 if any critical.
     */
    public function api()
    {
        $data    = $this->health->all();
        $summary = $data['summary'];
        $status  = $summary['critical'] > 0 ? 503 : 200;

        return response()->json($data, $status);
    }
}
