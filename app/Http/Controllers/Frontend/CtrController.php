<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CtrController extends Controller
{
    public function __invoke(Request $request)
    {
        if (!app(\App\Services\Performance\PlayerRatingAccess::class)->allowsMarketing($request->user())) {
            return response('Forbidden', 403)->header('Cache-Control', 'no-store, private');
        }

        $requestedStage = $request->query('stage', 'red');
        $stage = is_string($requestedStage) && in_array($requestedStage, ['red', 'orange', 'Wilson B', 'Wilson A', 'TSA tournaments'], true) ? $requestedStage : 'red';

        return response()->view('frontend.ctr', [
            'isSuperUser' => (bool) $request->user()?->hasRole('super-user'),
            'publicPathwayEvents' => $stage === 'TSA tournaments' ? ['stage' => $stage, 'events' => [], 'limited' => false] : app(\App\Services\Performance\PlayerPathwayEvents::class)->forStage($stage),
            'publicPathwayStage' => $stage,
        ])->header('Cache-Control', 'no-store, private');
    }
}
