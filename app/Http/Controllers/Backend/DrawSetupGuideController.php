<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Draw, Event};
use App\Services\Draw\DrawSetupGuide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class DrawSetupGuideController extends Controller
{
    private function authorizeGuide(Event $event, Draw $draw): void
    {
        abort_unless((int) $draw->event_id === (int) $event->id, 404);
        $this->authorize('event.manage', $event);
        $this->authorize('view', $draw);
    }

    public function show(Request $request, Event $event, Draw $draw, DrawSetupGuide $guide)
    {
        $this->authorizeGuide($event, $draw);
        $data = ['draw' => $draw, 'event' => $event, 'guide' => $guide->state($draw),
            'categories' => $event->categoryEvents()->with('category')->get()];
        if ($request->expectsJson()) return response()->json(['html' => view('backend.draw.partials.guide-content', $data)->render(), 'guide_url' => route('draw.guide.show', [$event, $draw])])->header('Cache-Control', 'private, no-store');
        return response()->view('backend.draw.guide', $data)->header('Cache-Control', 'private, no-store');
    }

    public function category(Request $request, Event $event, Draw $draw)
    {
        $this->authorizeGuide($event, $draw);
        $this->authorize('update', $draw);
        $data = $request->validate(['category_event_id' => ['required', 'integer', Rule::exists('category_events', 'id')->where('event_id', $event->id)]]);
        DB::transaction(function () use ($draw, $data) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            abort_if($draw->isTeamDraw() || $draw->locked || $draw->published || $draw->oop_published || $draw->category_event_id
                || $draw->drawFixtures()->exists() || $draw->registrations()->reorder()->exists() || $draw->groups()->exists()
                || !empty($draw->flexibleMonrad?->draft['slots']) || !empty($draw->flexibleMonrad?->graph), 409, 'Review this draw in its existing workspace before changing its category.');
            $draw->update($data);
        });
        if ($request->expectsJson()) return response()->json(['guide_url' => route('draw.guide.show', [$event, $draw])]);
        return redirect()->route('draw.guide.show', [$event, $draw]);
    }

    public function generateTeam(Request $request, Event $event, Draw $draw)
    {
        $this->authorizeGuide($event, $draw);
        $this->authorize('team-fixture.update', $draw);
        $request->validate(['confirm_generation' => ['accepted']]);
        DB::transaction(function () use ($draw) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            abort_unless($draw->isTeamDraw(), 422);
            app(\App\Services\TeamDrawMutationGuard::class)->destructive($draw);
            abort_if($draw->fixtures()->exists() || $draw->teamTies()->exists(), 409, 'Team ties already exist. Review them in the team workspace.');
            $teams = $draw->teams_in_draw()->get();
            abort_if($teams->count() < 2 || empty($draw->team_format_snapshot['rubbers']), 409, 'Review the selected teams and format first.');
            $ties = app(\App\Services\TeamDrawGenerationService::class)->generate($draw, $teams);
            foreach ($ties as $tie) app(\App\Services\TeamTieGenerationService::class)->generateForTie($tie);
        });
        if ($request->expectsJson()) return response()->json(['guide_url' => route('draw.guide.show', [$event, $draw])]);
        return redirect()->route('draw.guide.show', [$event, $draw]);
    }
}
