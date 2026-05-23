<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Engine\EngineRouter;
use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\EngineMismatch;
use App\Models\EngineRun;
use App\Models\Event;
use Illuminate\Http\Request;

/**
 * DrawEngineModeController
 *
 * Allows admins to set per-draw and per-event engine mode overrides.
 * Enforces the canonical safety guard (no unresolved P0/P1 mismatches).
 * Provides a rollback action to instantly reset a draw to legacy mode.
 */
class DrawEngineModeController extends Controller
{
    private const VALID_MODES = ['legacy', 'hybrid', 'canonical'];

    public function show(Draw $draw)
    {
        $draw->load('event');

        $safetyCheck = EngineRouter::canonicalSafetyCheck($draw);

        $runStats = EngineRun::forDraw($draw->id)
            ->selectRaw('engine_mode,
                count(*) as total,
                sum(case when canonical_success = 1 then 1 else 0 end) as canon_ok,
                sum(case when fallback_used = 1 then 1 else 0 end) as fallbacks,
                sum(case when mismatch_detected = 1 then 1 else 0 end) as mismatches')
            ->groupBy('engine_mode')
            ->get();

        $recentMismatches = EngineMismatch::forDraw($draw->id)
            ->unresolved()
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $confidence = EngineRun::confidenceScore();

        return view('backend.engine.draw-mode', compact(
            'draw',
            'safetyCheck',
            'runStats',
            'recentMismatches',
            'confidence',
        ));
    }

    public function update(Request $request, Draw $draw)
    {
        $request->validate([
            'engine_mode' => ['nullable', 'in:legacy,hybrid,canonical,'],
        ]);

        $mode = $request->input('engine_mode') ?: null;

        // Safety guard: block canonical if unresolved P0/P1 mismatches
        if ($mode === 'canonical') {
            $check = EngineRouter::canonicalSafetyCheck($draw);
            if (! $check['allowed']) {
                return back()->withErrors(['engine_mode' => $check['reason']]);
            }
        }

        $draw->update(['engine_mode' => $mode]);

        $label = $mode ?? 'inherit (global)';
        return back()->with('success', "Draw #{$draw->id} engine mode set to: {$label}");
    }

    public function rollback(Draw $draw)
    {
        $previous = $draw->engine_mode;
        $draw->update(['engine_mode' => 'legacy']);

        // Mark all unresolved mismatches for this draw as resolved
        EngineMismatch::forDraw($draw->id)->unresolved()->update(['resolved' => true]);

        return back()->with('success',
            "Draw #{$draw->id} rolled back to LEGACY mode (was: " . ($previous ?? 'inherit') . "). All mismatches marked resolved.");
    }

    public function updateEvent(Request $request, Event $event)
    {
        $request->validate([
            'engine_mode' => ['nullable', 'in:legacy,hybrid,canonical,'],
        ]);

        $mode = $request->input('engine_mode') ?: null;

        // If setting event to canonical, check all draws in the event
        if ($mode === 'canonical') {
            $blocked = Draw::where('event_id', $event->id)
                ->get()
                ->filter(fn($d) => ! EngineRouter::canonicalSafetyCheck($d)['allowed']);

            if ($blocked->isNotEmpty()) {
                $ids = $blocked->pluck('id')->join(', ');
                return back()->withErrors([
                    'engine_mode' => "Canonical blocked: draws [{$ids}] have unresolved HIGH/MEDIUM mismatches.",
                ]);
            }
        }

        $event->update(['engine_mode' => $mode]);

        $label = $mode ?? 'inherit (global)';
        return back()->with('success', "Event #{$event->id} engine mode set to: {$label}");
    }
}
