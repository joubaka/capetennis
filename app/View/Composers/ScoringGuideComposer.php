<?php

namespace App\View\Composers;

use App\Models\EventConvenor;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ScoringGuideComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();
        $isGuideAccount = $user && strcasecmp(trim((string) $user->email), 'convenor@capetennis.co.za') === 0;
        $assignments = $isGuideAccount ? EventConvenor::query()
            ->where('user_id', $user->id)->active()->with('event:id,name')
            ->orderBy('event_id')->limit(20)->get()
            ->filter(fn ($assignment) => $assignment->event && Gate::forUser($user)->allows('event.score', $assignment->event))
            ->unique('event_id') : collect();

        $automatic = false;
        if ($assignments->isNotEmpty() && request()->hasSession()) {
            $key = 'scoring_guide.presented.'.$user->id;
            $automatic = ! request()->session()->get($key, false);
            request()->session()->put($key, true);
        }

        $view->with('scoringGuideAssignments', $assignments)
            ->with('scoringGuideAutomatic', $automatic);
    }
}
