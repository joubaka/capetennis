<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\{Draw, Event};
use App\Services\Draw\LuckyPrizeService;
use Illuminate\Http\Request;

final class LuckyPrizeController extends Controller
{
    public function pool(Event $event, Draw $draw, LuckyPrizeService $service)
    {
        abort_unless((int) $draw->event_id === (int) $event->id, 404);
        $this->authorize('awardLuckyPrize', $draw);
        return response()->json($service->pool($draw));
    }

    public function spin(Request $request, Event $event, Draw $draw, LuckyPrizeService $service)
    {
        abort_unless((int) $draw->event_id === (int) $event->id, 404);
        $this->authorize('awardLuckyPrize', $draw);
        $data = $request->validate(['token' => 'required|uuid', 'revision' => 'required|string|size:64']);
        return response()->json($service->spin($draw, $data['token'], $data['revision']));
    }
}
