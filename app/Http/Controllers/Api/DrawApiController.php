<?php

namespace App\Http\Controllers\Api;

use App\Domain\Draws\Exceptions\DrawMutationException;
use App\Domain\Draws\Guards\DrawGuard;
use App\Domain\Draws\Services\BracketRenderService;
use App\Domain\Engine\EngineRouter;
use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\DrawAuditLog;
use App\Models\Fixture;
use App\Services\DrawService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DrawApiController extends Controller
{
    public function __construct(
        protected DrawService         $service,
        protected EngineRouter        $engine,
        protected BracketRenderService $bracketRenderer,
    ) {}

    // ─────────────────────────────────────────────
    // GET /api/draws/{draw}/hub
    // ─────────────────────────────────────────────
    public function hub(Draw $draw)
    {
        $this->authorize('view', $draw);

        $hub = $this->service->loadRoundRobinHub($draw);

        return response()->json([
            'success'    => true,
            'locked'     => (bool) $draw->locked,
            'published'  => (bool) $draw->published,
            'engineMode' => $draw->engine_mode ?? 'legacy',
            'standings'  => $hub['standings'] ?? [],
            'rrFixtures' => $hub['rrFixtures'] ?? [],
            'oops'       => $hub['oops'] ?? [],
        ]);
    }

    // ─────────────────────────────────────────────
    // POST /api/draws/{draw}/fixtures/{fixture}/score
    // ─────────────────────────────────────────────
    public function storeScore(Request $request, Draw $draw, Fixture $fixture)
    {
        $this->authorize('saveScore', $draw);

        // Published draws remain scoreable; locking is the operational stop.
        if ($draw->locked) {
            return response()->json(['success' => false, 'message' => 'Draw is locked.'], 403);
        }

        // Guard: fixture must belong to this draw
        if ((int) $fixture->draw_id !== (int) $draw->id) {
            return response()->json(['success' => false, 'message' => 'Fixture does not belong to this draw.'], 422);
        }

        // Guard: fixture must be scoreable (not verified)
        try {
            DrawGuard::requireScoreable($fixture);
        } catch (DrawMutationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }

        $validated = $request->validate([
            'sets'   => 'required|array|min:1',
            'sets.*' => ['required', 'string', 'regex:/^\d+-\d+$/'],
        ]);

        $validSets = array_map(function ($set) {
            [$a, $b] = array_map('intval', explode('-', $set));
            return [$a, $b];
        }, $validated['sets']);

        $response = DB::transaction(function () use ($draw, $fixture, $validSets) {
            if ($fixture->stage === 'RR') {
                $resp = $this->service->saveScore($fixture, $validSets);
                $hub  = $this->service->loadRoundRobinHub($draw);
                $resp['standings']  = $hub['standings'] ?? [];
                $resp['rrFixtures'] = $hub['rrFixtures'] ?? [];
                $resp['oops']       = $hub['oops'] ?? [];
                return $resp;
            }

            // Bracket stages — route through the service (which uses EngineRouter internally)
            return $this->service->saveBracketScore($fixture, $validSets);
        });

        DrawAuditLog::record($draw->id, 'score_saved', $fixture->id, [
            'stage' => $fixture->stage,
            'sets'  => $validSets,
        ]);

        return response()->json(['success' => true] + $response);
    }

    // ─────────────────────────────────────────────
    // DELETE /api/draws/{draw}/fixtures/{fixture}/score
    // ─────────────────────────────────────────────
    public function deleteScore(Draw $draw, Fixture $fixture)
    {
        // Guard before authorize so JSON body is preserved for locked/published draws
        if ($draw->locked) {
            return response()->json(['success' => false, 'message' => 'Draw is locked.'], 403);
        }

        $this->authorize('deleteScore', $draw);

        // Guard: fixture must belong to this draw
        if ((int) $fixture->draw_id !== (int) $draw->id) {
            return response()->json(['success' => false, 'message' => 'Fixture does not belong to this draw.'], 422);
        }

        // Load results before the transaction clears them
        $fixture->loadMissing('fixtureResults');

        DB::transaction(function () use ($draw, $fixture) {
            // Route rollback through EngineRouter (canonical or legacy depending on draw mode)
            $this->engine->forDraw($draw)->rollbackFixture(
                $fixture,
                fn(Fixture $fx) => $this->_legacyRollback($fx)
            );
        });

        DrawAuditLog::record($draw->id, 'score_deleted', $fixture->id, [
            'stage' => $fixture->stage,
        ]);

        // Return fresh hub data so RR state updates correctly
        $hub = $this->service->loadRoundRobinHub($draw);

        return response()->json([
            'success'    => true,
            'message'    => 'Score deleted',
            'standings'  => $hub['standings'] ?? [],
            'rrFixtures' => $hub['rrFixtures'] ?? [],
            'oops'       => $hub['oops'] ?? [],
        ]);
    }

    // ─────────────────────────────────────────────
    // GET /api/draws/{draw}/brackets
    // ─────────────────────────────────────────────
    public function brackets(Draw $draw)
    {
        $this->authorize('view', $draw);

        $stages = array_values(array_filter(
            ['MAIN', 'PLATE', 'CONS', 'BOWL', 'SHIELD', 'SPOON'],
            fn($s) => $draw->drawFixtures()->where('stage', $s)->exists()
        ));

        if (empty($stages)) {
            return response()->json([
                'success' => true,
                'stages'  => [],
                'message' => 'No bracket fixtures generated yet.',
            ]);
        }

        $data = $this->bracketRenderer->buildBracketData($draw, $stages);

        return response()->json([
            'success'    => true,
            'locked'     => (bool) $draw->locked,
            'published'  => (bool) $draw->published,
            'engineMode' => $draw->engine_mode ?? 'legacy',
            'stages'     => $data['stages'],
        ]);
    }

    // ─────────────────────────────────────────────
    // POST /api/draws/{draw}/brackets/generate
    // ─────────────────────────────────────────────
    public function generateBrackets(Request $request, Draw $draw)
    {
        $this->authorize('generateBrackets', $draw);

        if ($draw->locked) {
            return response()->json(['success' => false, 'message' => 'Draw is locked.'], 403);
        }
        if ($draw->published) {
            return response()->json(['success' => false, 'message' => 'Draw is published.'], 403);
        }

        // Delegate to the web controller which contains the full generation logic.
        // This keeps a single code path during the transition period.
        return app(\App\Http\Controllers\Backend\RoundRobinController::class)
            ->generateMainBracket($request, $draw);
    }

    // ─────────────────────────────────────────────
    // POST /api/draws/{draw}/groups
    // ─────────────────────────────────────────────
    public function saveGroups(Request $request, Draw $draw)
    {
        $this->authorize('modifyGroups', $draw);

        return response()->json(app(\App\Services\Draw\GroupAssignmentService::class)->save($draw, $request->all()));
    }

    // ─────────────────────────────────────────────
    // POST /api/draws/{draw}/schedule
    // ─────────────────────────────────────────────
    public function saveSchedule(Request $request, Draw $draw)
    {
        $this->authorize('modifySchedule', $draw);

        $validated = $request->validate([
            'items'                => 'required|array',
            'items.*.fixture_id'   => 'required|integer|exists:fixtures,id',
            'items.*.court'        => 'nullable|string|max:50',
            'items.*.venue_id'     => 'nullable|integer|exists:venues,id',
            'items.*.start_time'   => 'nullable|string|max:50',
            'items.*.duration_minutes' => 'nullable|integer|min:1|max:300',
            'items.*.round'        => 'nullable|string|max:50',
        ]);

        $venueId = $draw->venues->first()->id ?? 1;

        $slots = [];
        $intervals = [];
        $fixturesById = $draw->drawFixtures()
            ->whereIn('id', collect($validated['items'])->pluck('fixture_id'))
            ->get()
            ->keyBy('id');
        foreach ($validated['items'] as $item) {
            $rawTime = $item['start_time'] ?? null;
            if (! $rawTime || empty($item['court'])) {
                continue;
            }

            $timeValue = (strlen($rawTime) <= 8)
                ? now()->toDateString() . ' ' . $rawTime
                : $rawTime;
            $itemVenueId = $item['venue_id'] ?? $venueId;
            if ($draw->venues->isNotEmpty() && ! $draw->venues->contains('id', $itemVenueId)) {
                return response()->json(['success' => false, 'message' => 'The selected venue is not assigned to this draw.'], 422);
            }
            $slotKey = $itemVenueId . '|' . $item['court'] . '|' . $timeValue;
            if (isset($slots[$slotKey])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule conflict: two matches use the same venue, court and start time.',
                    'conflict' => ['fixture_ids' => [$slots[$slotKey], $item['fixture_id']]],
                ], 422);
            }
            $slots[$slotKey] = $item['fixture_id'];

            $start = Carbon::parse($timeValue);
            $end = $start->copy()->addMinutes((int) ($item['duration_minutes'] ?? 75));
            foreach ($intervals as $interval) {
                if ($interval['venue_id'] === $itemVenueId && $interval['court'] === $item['court']
                    && $start->lt($interval['end'] ?? $interval['start'])
                    && $end->gt($interval['start'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Schedule conflict: match times overlap on the same court.',
                        'conflict' => ['fixture_ids' => [$interval['fixture_id'], $item['fixture_id']]],
                    ], 422);
                }
                $currentFixture = $fixturesById->get($item['fixture_id']);
                $previousFixture = $fixturesById->get($interval['fixture_id']);
                $currentPlayers = collect([$currentFixture?->registration1_id, $currentFixture?->registration2_id])->filter();
                $previousPlayers = collect([$previousFixture?->registration1_id, $previousFixture?->registration2_id])->filter();
                if ($start->lt($interval['end']) && $end->gt($interval['start'])
                    && $currentPlayers->intersect($previousPlayers)->isNotEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Schedule conflict: a participant is scheduled in overlapping matches.',
                        'conflict' => ['fixture_ids' => [$interval['fixture_id'], $item['fixture_id']]],
                    ], 422);
                }
            }
            $intervals[] = ['fixture_id' => $item['fixture_id'], 'venue_id' => $itemVenueId, 'court' => $item['court'], 'start' => $start, 'end' => $end];
        }

        DB::transaction(function () use ($draw, $validated, $venueId) {
                foreach ($validated['items'] as $item) {
                    $fixture = $draw->drawFixtures()->find($item['fixture_id']);
                    if (!$fixture) continue;

                    $rawTime = $item['start_time'] ?? null;
                    $timeValue = null;
                    if ($rawTime) {
                        // Accept either full datetime or time-only (HH:MM or HH:MM:SS)
                        $timeValue = (strlen($rawTime) <= 8)
                            ? now()->toDateString() . ' ' . $rawTime
                            : $rawTime;
                    }

                    \App\Models\OrderOfPlay::updateOrCreate(
                        ['fixture_id' => $fixture->id],
                        [
                            'draw_id'  => $draw->id,
                            'court'    => $item['court'] ?? null,
                            'time'     => $timeValue,
                            'venue_id' => $item['venue_id'] ?? $venueId,
                        ]
                    );
                }
            });

        DrawAuditLog::record($draw->id, 'schedule_saved', null, [
            'item_count' => count($validated['items']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Schedule saved',
        ]);
    }

    // ─────────────────────────────────────────────
    // GET /api/draws/{draw}/schedule/summary
    // ─────────────────────────────────────────────
    public function scheduleSummary(Draw $draw)
    {
        $this->authorize('view', $draw);

        $schedule = \App\Models\OrderOfPlay::with([
                'fixture.registration1.players',
                'fixture.registration2.players',
            ])
            ->whereHas('fixture', fn ($q) => $q->where('draw_id', $draw->id))
            ->orderBy('time')
            ->orderBy('court')
            ->get()
            ->map(fn ($oop) => [
                'fixture_id' => $oop->fixture_id,
                'stage'      => optional($oop->fixture)->stage,
                'round'      => optional($oop->fixture)->round,
                'match_nr'   => optional($oop->fixture)->match_nr,
                'court'      => $oop->court,
                'start_time' => $oop->time,
                'home'       => optional(optional($oop->fixture)->registration1)->display_name ?? '',
                'away'       => optional(optional($oop->fixture)->registration2)->display_name ?? '',
            ]);

        return response()->json([
            'success'  => true,
            'schedule' => $schedule,
        ]);
    }

    // ─────────────────────────────────────────────
    // PRIVATE: legacy rollback closure
    // Aligned with FixtureProgressionService::rollback() logic.
    // ─────────────────────────────────────────────
    private function _legacyRollback(Fixture $fx): void
    {
        // Winner slot rollback
        if ($fx->parent_fixture_id) {
            $parent = Fixture::find($fx->parent_fixture_id);
            if ($parent) {
                $siblings  = Fixture::where('parent_fixture_id', $parent->id)->orderBy('match_nr')->pluck('id');
                $slotIndex = $siblings->search($fx->id);
                $field     = ($slotIndex === false || $slotIndex === 0) ? 'registration1_id' : 'registration2_id';

                if ($parent->{$field} === $fx->winner_registration) {
                    $parent->{$field} = null;
                }
                if ($parent->winner_registration === $fx->winner_registration) {
                    $parent->winner_registration = null;
                    $parent->match_status        = 0;
                }
                $parent->save();
            }
        }

        // Loser slot rollback
        if ($fx->loser_parent_fixture_id) {
            $loserParent = Fixture::find($fx->loser_parent_fixture_id);
            if ($loserParent) {
                $loserId  = $fx->winner_registration === $fx->registration1_id
                    ? $fx->registration2_id
                    : $fx->registration1_id;

                $siblings  = Fixture::where('loser_parent_fixture_id', $loserParent->id)->orderBy('match_nr')->pluck('id');
                $slotIndex = $siblings->search($fx->id);
                $field     = ($slotIndex === false || $slotIndex === 0) ? 'registration1_id' : 'registration2_id';

                if ($loserId && $loserParent->{$field} === $loserId) {
                    $loserParent->{$field} = null;
                }
                if ($loserParent->winner_registration === $loserId) {
                    $loserParent->winner_registration = null;
                    $loserParent->match_status        = 0;
                }
                $loserParent->save();
            }
        }

        // Clear this fixture's result
        $fx->fixtureResults()->delete();
        $fx->winner_registration = null;
        $fx->match_status        = 0;
        $fx->save();
    }
}
