<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CtrController extends Controller
{
    public function __invoke(Request $request)
    {
        if (!$request->user()?->hasRole('super-user')) {
            return response('Forbidden', 403)->header('Cache-Control', 'no-store, private');
        }

        return response()->view('frontend.ctr', [
            'canSeeCtr' => true,
            'isSuperUser' => true,
        ])->header('Cache-Control', 'no-store, private');
    }
}
