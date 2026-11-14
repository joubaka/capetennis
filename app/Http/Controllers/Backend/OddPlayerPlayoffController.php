<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Services\Draw\OddPlayerPlayoffService;
use Illuminate\Http\Request;

final class OddPlayerPlayoffController extends Controller
{
    public function store(Request $request, Draw $draw, OddPlayerPlayoffService $service)
    {
        $this->authorize('progress', $draw);
        $data = $request->validate(['event_id' => 'required|integer', 'snapshot' => 'required|string|size:64', 'confirmed' => 'accepted']);
        $service->apply($draw, (int) $data['event_id'], $data['snapshot'], $request->user());
        return redirect()->route('backend.draw.roundrobin.show', $draw)->with('success', 'Odd-player playoff enabled. Existing times are preserved; schedule the new preliminary before its final. Review and confirm group progression next.');
    }
}
