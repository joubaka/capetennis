<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Services\Draw\InterproScoringCorrectionService;
use Illuminate\Http\Request;

final class InterproScoringCorrectionController extends Controller
{
    public function show(Request $request,Draw $draw,InterproScoringCorrectionService $service)
    {
        $this->authorize('correctRoundRobinScoring',$draw);
        $playoffs=$this->playoffs($request);
        return response()->view('backend.draw.roundrobin.interpro-scoring-correction',['draw'=>$draw,'review'=>$playoffs ? $service->reviewPlayoffs($draw) : $service->review($draw,$this->points($request))])
            ->header('Cache-Control','private, no-store');
    }

    public function store(Request $request,Draw $draw,InterproScoringCorrectionService $service)
    {
        $this->authorize('correctRoundRobinScoring',$draw);
        $data=$request->validate(['revision'=>'required|string|size:64','acknowledge_winner_change'=>'sometimes|boolean']);
        $playoffs=$this->playoffs($request);
        $review=$playoffs ? $service->applyPlayoffs($draw,$data['revision'],$request->user()) : $service->apply($draw,$data['revision'],$request->user(),$this->points($request),$request->boolean('acknowledge_winner_change'));
        if ($request->expectsJson()) return response()->json(['success'=>true,'validated_results'=>$review['compatible']]);
        if ($playoffs) return redirect()->route('backend.draw.roundrobin.show',$draw)->with('success','Playoffs now use two full sets from 0–0 and a deciding 10-point match tiebreak, won by two. Round-robin rules, recorded scores and schedules were retained.');
        $corrected=count($review['corrections']);
        return redirect()->route('backend.draw.roundrobin.show',$draw)->with('success',
            'Round-robin and playoff rules now use two sets from 2–2 and a deciding 10-point match tiebreak. '.
            ($corrected ? "Corrected {$corrected} deciding score(s) using the reviewed actual points. First two sets and schedules were retained." : 'Existing scores and schedules were retained.'));
    }

    private function playoffs(Request $request): bool
    {
        $request->validate(['scope'=>'sometimes|in:playoffs','third_set_points'=>'prohibited_if:scope,playoffs']);
        return $request->input('scope')==='playoffs';
    }

    private function points(Request $request): array
    {
        $data=$request->validate(['third_set_points'=>'sometimes|array|max:100','third_set_points.*'=>'array|size:2',
            'third_set_points.*.*'=>'nullable|integer|min:0|max:999']);
        $points=[];
        foreach ($data['third_set_points'] ?? [] as $id=>$scores) {
            if (($scores[0] ?? null)===null && ($scores[1] ?? null)===null) continue;
            abort_unless(isset($scores[0],$scores[1]),422,'Supply both actual deciding-tiebreak point totals.');
            $points[$id]=[(int)$scores[0],(int)$scores[1]];
        }
        ksort($points);
        return $points;
    }
}
