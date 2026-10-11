<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\AgeGroupConvenerStandingsService;
use Illuminate\Http\Request;

final class AgeGroupConvenerStandingsController extends Controller
{
    public function __invoke(Request $request, Event $event, string $age, AgeGroupConvenerStandingsService $standings)
    {
        return response()->view('frontend.fixtures.convener-standings', [
            'event' => $event, 'age' => $age,
            'standings' => $standings->standings($event, $request->user(), $age),
        ])->header('Cache-Control', 'private, no-store');
    }
}
