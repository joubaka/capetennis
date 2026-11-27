<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Services\Draw\InterproChallengeSelectionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class InterproChallengeSelectionController extends Controller
{
    public function __invoke(Request $request,Draw $draw,InterproChallengeSelectionService $service)
    {
        $this->authorize('appendInterproChallenges',$draw);
        $data=$request->validate(['challenge_pairs'=>['required','array','min:1','max:5'],
            'challenge_pairs.*'=>['required','integer','distinct',Rule::in([2,4,6,8,10])],
            'revision'=>['required','string','size:64']]);
        $count=$service->append($draw,$data['challenge_pairs'],$data['revision']);
        if ($request->expectsJson()) return response()->json(['success'=>true,'appended'=>$count]);
        return redirect()->route('backend.draw.roundrobin.show',$draw)->with('success',
            $count ? "Added {$count} challenge matches. Existing matches, schedules and publication were retained. Schedule the new matches after the base playoffs." : 'These challenge matches already exist.');
    }
}
