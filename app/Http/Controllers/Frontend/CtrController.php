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

        return response()->view('frontend.ctr', [
            'isSuperUser' => (bool) $request->user()?->hasRole('super-user'),
        ])->header('Cache-Control', 'no-store, private');
    }
}
