<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Engine\EngineRouter;
use App\Http\Controllers\Controller;
use App\Models\EngineComparisonLog;
use App\Models\EngineMismatch;
use App\Models\EngineRun;

/**
 * EngineDebugController
 *
 * Read-only admin dashboard: engine mode, mismatch trends, fallback frequency,
 * canonical confidence score, top mismatch types, recent failures.
 */
class EngineDebugController extends Controller
{
    public function index(EngineRouter $engine)
    {
        // --- legacy comparison log stats (original table)
        $totalMismatches = EngineComparisonLog::where('was_fallback', false)->count();
        $totalFallbacks  = EngineComparisonLog::where('was_fallback', true)->count();

        $byOperation = EngineComparisonLog::selectRaw('operation, count(*) as total, sum(was_fallback) as fallbacks')
            ->groupBy('operation')
            ->orderByDesc('total')
            ->get();

        $recentMismatches = EngineComparisonLog::where('was_fallback', false)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $recentFallbacks = EngineComparisonLog::where('was_fallback', true)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // --- engine_runs stats
        $runStats = [
            'total'     => EngineRun::count(),
            'canonical' => EngineRun::where('canonical_success', true)->count(),
            'fallbacks' => EngineRun::where('fallback_used', true)->count(),
            'failures'  => EngineRun::where('canonical_success', false)->count(),
            'avg_ms'    => (int) EngineRun::avg('duration_ms'),
        ];

        $runsByOperation = EngineRun::selectRaw('operation_type, count(*) as total,
            sum(case when canonical_success = 1 then 1 else 0 end) as canon_ok,
            sum(case when fallback_used = 1 then 1 else 0 end) as fallbacks,
            sum(case when mismatch_detected = 1 then 1 else 0 end) as mismatches,
            round(avg(duration_ms)) as avg_ms')
            ->groupBy('operation_type')
            ->orderByDesc('total')
            ->get();

        $recentFailedRuns = EngineRun::where('canonical_success', false)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // --- engine_mismatches stats
        $topMismatchTypes   = EngineMismatch::topTypes(10);
        $unresolvedHighSev  = EngineMismatch::unresolved()->highSeverity()->count();
        $recentEngineMismatches = EngineMismatch::unresolved()
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        // --- confidence score
        $confidence = EngineRun::confidenceScore();

        // --- runtime counters (current request lifecycle only)
        $runtimeMismatches = EngineRouter::mismatchCount();
        $runtimeFallbacks  = EngineRouter::fallbackCount();

        return view('backend.engine.debug', compact(
            'engine',
            'totalMismatches',
            'totalFallbacks',
            'byOperation',
            'recentMismatches',
            'recentFallbacks',
            'runStats',
            'runsByOperation',
            'recentFailedRuns',
            'topMismatchTypes',
            'unresolvedHighSev',
            'recentEngineMismatches',
            'confidence',
            'runtimeMismatches',
            'runtimeFallbacks',
        ));
    }

    public function clearLogs()
    {
        EngineComparisonLog::truncate();
        EngineRun::truncate();
        EngineMismatch::truncate();
        EngineRouter::resetCounters();

        return redirect()->route('engine.debug')->with('success', 'All engine logs cleared.');
    }
}

