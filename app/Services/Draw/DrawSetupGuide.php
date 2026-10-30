<?php

namespace App\Services\Draw;

use App\Models\{Draw, DrawGroup, Fixture, OrderOfPlay};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/** Read-only progress derived from the current tournament records. */
final class DrawSetupGuide
{
    /** Compact event-scoped overview; no full workspace rendering, scoring or withdrawal reconciliation. */
    public function overview(Collection $draws, Collection $scheduleStates): Collection
    {
        $draws = $draws->reject(fn ($draw) => $draw->isTeamDraw());
        if ($draws->isEmpty()) return collect();
        $fixtures = Fixture::whereIn('draw_id', $draws->pluck('id'))
            ->select(['id', 'draw_id', 'draw_group_id', 'stage', 'registration1_id', 'registration2_id', 'winner_registration'])
            ->withCount('fixtureResults')->get()->groupBy('draw_id');
        $schedules = OrderOfPlay::query()->leftJoin('venues', 'venues.id', '=', 'order_of_plays.venue_id')
            ->whereIn('fixture_id', $fixtures->flatten(1)->pluck('id'))
            ->get(['fixture_id', 'time', 'court', 'venue_id', 'venues.name as venue_name'])->keyBy('fixture_id');
        $roundRobinIds = $draws->filter(fn ($draw) => in_array($draw->settings?->workflow, ['round_robin', 'round_robin_playoffs'], true))->pluck('id');
        $groups = $roundRobinIds->isEmpty() ? collect() : DrawGroup::whereIn('draw_id', $roundRobinIds)->with('groupRegistrations')->get()->groupBy('draw_id');

        return $draws->mapWithKeys(function (Draw $draw) use ($fixtures, $schedules, $groups, $scheduleStates) {
            $matches = $fixtures->get($draw->id, collect());
            $participants = (int) $draw->registrations_count;
            $warnings = [];
            $pendingWithdrawals = 0;
            if ($draw->usesFlexibleMonrad()) {
                $record = $draw->flexibleMonrad;
                $graph = $record?->graph;
                $participants = collect($record?->draft['slots'] ?? [])->where('type', 'player')->count();
                $playable = collect();
                if ($graph && !$this->overviewGraphValid($graph, $record->fixture_map ?? [])) {
                    $warnings[] = 'Generated match records need recovery in the draw workspace.';
                    $graph = null;
                }
                if ($graph) {
                    $map = $record->fixture_map ?? [];
                    $byId = $matches->keyBy('id');
                    $input = [];
                    foreach ($map as $key => $id) {
                        $fixture = $byId->get($id);
                        if (!$fixture) { $warnings[] = 'Generated match records need recovery in the draw workspace.'; continue; }
                        $input[$key] = ['players' => [$fixture->registration1_id ? (int) $fixture->registration1_id : null,
                            $fixture->registration2_id ? (int) $fixture->registration2_id : null],
                            'winner' => $fixture->winner_registration ? (int) $fixture->winner_registration : null, 'played' => $fixture->fixture_results_count > 0];
                    }
                    if (count($map) !== $matches->count() || array_diff(array_keys($graph['nodes'] ?? []), array_keys($map))) $warnings[] = 'Generated match records need review.';
                    if (!$warnings) {
                        try {
                            $progression = app(FlexibleMonradProgression::class)->resolve($graph, $input, $graph['withdrawn'] ?? []);
                            $playable = collect($progression['matches'])->reject(fn ($match) => $match['automatic'])->map(fn ($match, $key) => $map[$key]);
                        } catch (\LogicException $exception) {
                            $warnings[] = 'Generated match records need recovery in the draw workspace.';
                        }
                    }
                    $active = app(FlexibleMonradService::class)->activeRegistrationIds($draw, $graph['players'] ?? []);
                    $pendingWithdrawals = count(array_diff($graph['players'] ?? [], $active, $graph['withdrawn'] ?? []));
                    if ($pendingWithdrawals) $warnings[] = 'Review withdrawn players in the draw before continuing.';
                }
                $scheduled = $playable->filter(function ($id) use ($schedules) {
                    $slot = $schedules->get($id);
                    return $slot?->time && !empty($slot->court) && !empty($slot->venue_name);
                })->count();
            } else {
                $playable = $matches->reject(fn ($fixture) => $fixture->winner_registration && (!$fixture->registration1_id || !$fixture->registration2_id));
                $scheduled = $playable->filter(function ($fixture) use ($schedules) {
                    $slot = $schedules->get($fixture->id);
                    return $slot?->time && $slot->venue_id && trim((string) $slot->court) !== '';
                })->count();
            }
            if (in_array($draw->settings?->workflow, ['round_robin', 'round_robin_playoffs'], true)
                && app(GroupAssignmentService::class)->fixturesNeedRegeneration($draw, $matches->where('stage', 'RR'), $groups->get($draw->id, collect()))) $warnings[] = 'Saved groups differ from the generated matches.';
            $generated = $matches->isNotEmpty() && $playable->isNotEmpty();
            $category = (int) $draw->categoryEvent?->event_id === (int) $draw->event_id;
            $eventWide = !$draw->category_event_id && in_array($draw->settings?->workflow, ['round_robin', 'round_robin_playoffs'], true);
            $format = (bool) $draw->settings?->workflow || $generated || $draw->groups_count > 0 || $draw->settings?->draw_format_id;
            $scheduleComplete = $generated && $scheduled === $playable->count();
            $publication = $scheduleStates->get($draw->id, []);
            $snapshotCurrent = ($publication['saved'] ?? 0) > 0 && ($publication['pending'] ?? 1) === 0
                && ($publication['saved'] ?? 0) === ($publication['published'] ?? -1);
            $flags = $this->completionFlags($draw, $category || $eventWide || $participants > 0 || $generated,
                (bool) $format, $generated, $warnings, $scheduleComplete, $snapshotCurrent);
            $complete = !in_array(false, $flags, true) && !($draw->team_scoring_rules['undecided'] ?? false);
            return [$draw->id => ['complete' => $complete, 'pending_withdrawals' => $pendingWithdrawals]];
        });
    }

    private function overviewGraphValid(mixed $graph, mixed $map): bool
    {
        if (!is_array($graph) || !is_array($map) || !is_array($graph['nodes'] ?? null) || !is_array($graph['players'] ?? null)
            || !is_array($graph['positions'] ?? null) || !is_array($graph['withdrawn'] ?? [])
            || !is_array($graph['base_positions'] ?? [])) return false;
        $sourceValid = fn ($source) => is_array($source) && (($source['type'] ?? null) === 'player'
            ? is_numeric($source['id'] ?? null)
            : in_array($source['type'] ?? null, ['winner', 'loser'], true) && is_string($source['match'] ?? null) && isset($graph['nodes'][$source['match']]));
        return collect($map)->every(fn ($id) => is_numeric($id))
            && collect($graph['players'])->every(fn ($id) => is_numeric($id))
            && collect($graph['nodes'])->every(fn ($node) => is_array($node) && is_array($node['sources'] ?? null)
                && count($node['sources']) === 2 && collect($node['sources'])->every($sourceValid))
            && collect($graph['positions'])->every($sourceValid) && collect($graph['base_positions'] ?? [])->every($sourceValid);
    }

    private function completionFlags(Draw $draw, bool $participantsReady, bool $formatReady, bool $generated, array $warnings, bool $scheduleComplete, bool $snapshotCurrent): array
    {
        return [true, $participantsReady, $formatReady, $generated && !$warnings, $scheduleComplete,
            $generated && $scheduleComplete && $snapshotCurrent && !$warnings && $draw->published && $draw->oop_published];
    }

    public function state(Draw $draw): array
    {
        $team = $draw->isTeamDraw();
        $competition = $draw->team_draw_selection['competition'] ?? null;
        $linkedPlayoff = $competition && !empty($competition['playoff_draw_id'])
            ? Draw::where('event_id', $draw->event_id)->find($competition['playoff_draw_id']) : null;
        $sourceDraw = $competition && !empty($competition['source_draw_id'])
            ? Draw::where('event_id', $draw->event_id)->find($competition['source_draw_id']) : null;
        $workspace = $team ? ($draw->team_format_snapshot !== null ? route('backend.team-draw.operations', $draw) : route('draws.manage', $draw)) : route('backend.draw.roundrobin.show', $draw);
        $participants = $team ? $draw->teams_in_draw()->count() : $draw->registrations()->reorder()->count();
        $category = $draw->categoryEvent()->where('event_id', $draw->event_id)->exists();
        $format = $team ? !empty($draw->team_format_snapshot['rubbers']) : (bool) $draw->settings?->workflow;
        $warnings = [];
        if ($team) {
            $fixtures = $draw->fixtures()->get();
            $playable = $fixtures;
            if ($draw->team_format_snapshot !== null && $fixtures->contains(fn ($fixture) => !$fixture->team_tie_id)) $warnings[] = 'Review team matches that are missing their tie.';
            $scheduled = $playable->filter(fn ($fixture) => $fixture->scheduled_at && $fixture->venue_id && trim((string) $fixture->court_label) !== '')->count();
            foreach ($draw->isPlacementDraft() ? [] : $draw->teamTies()->with('draw')->get() as $tie) {
                try { app(\App\Services\TeamTieValidationService::class)->assertTieComplete($tie); }
                catch (\InvalidArgumentException $exception) { $warnings[] = 'Review team lineups: '.$exception->getMessage(); break; }
            }
        } elseif ($draw->usesFlexibleMonrad()) {
            try { $state = app(FlexibleMonradService::class)->state($draw); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
                if ($exception->getStatusCode() !== 409) throw $exception;
                $warnings[] = 'Generated match records need recovery in the draw workspace.';
                $state = ['matches' => [], 'draft' => $draw->flexibleMonrad?->draft ?? [], 'withdrawals_pending' => false];
            }
            $playable = collect((array) $state['matches'])->reject(fn ($match) => $match['automatic']);
            $fixtures = $draw->drawFixtures()->get();
            $scheduled = $playable->filter(fn ($match) => !empty($match['schedule']['time']) && !empty($match['schedule']['court']) && !empty($match['schedule']['venue']))->count();
            $participants = collect((array) ($state['draft']['slots'] ?? []))->where('type', 'player')->count();
            if ($state['withdrawals_pending']) $warnings[] = 'Review withdrawn players in the draw before continuing.';
            if (count($draw->flexibleMonrad?->fixture_map ?? []) !== $fixtures->count()) $warnings[] = 'Generated match records need review.';
        } else {
            $fixtures = $draw->drawFixtures()->with('orderOfPlay')->get();
            $playable = $fixtures->reject(fn ($fixture) => $fixture->winner_registration && (!$fixture->registration1_id || !$fixture->registration2_id));
            $scheduled = $playable->filter(fn ($fixture) => $fixture->orderOfPlay?->time && $fixture->orderOfPlay?->venue_id && trim((string) $fixture->orderOfPlay?->court) !== '')->count();
        }
        $generated = $fixtures->isNotEmpty() && $playable->isNotEmpty();
        if (!$team && in_array($draw->settings?->workflow, ['round_robin', 'round_robin_playoffs'], true)
            && app(GroupAssignmentService::class)->fixturesNeedRegeneration($draw)) {
            $warnings[] = 'Saved groups differ from the generated matches. Review the saved groups and existing matches in player setup before continuing.';
        }
        $format = $format || $generated || (!$team && ($draw->groups()->exists() || $draw->settings?->draw_format_id));
        $eventWide = !$team && !$draw->category_event_id && in_array($draw->settings?->workflow, ['round_robin', 'round_robin_playoffs'], true);
        $scheduleComplete = $generated && $scheduled === $playable->count();
        $snapshotCurrent = $scheduleComplete && $this->snapshotCurrent($draw);
        $flags = $this->completionFlags($draw, $category || $eventWide || (!$team && ($participants > 0 || $generated)),
            (bool) $format, $generated, $warnings, $scheduleComplete, $snapshotCurrent);
        $steps = [
            ['label' => 'Competition', 'complete' => $flags[0], 'detail' => $team ? 'Team tie' : 'Individual singles', 'url' => route('headOffice.show', $draw->event_id)],
            ['label' => 'Participants', 'complete' => $flags[1], 'detail' => $category ? ($draw->categoryEvent?->category?->name ?? 'Category selected') : ($eventWide ? 'Players from multiple event categories.' : 'Choose the players or category for this draw.'), 'url' => $team ? route('draws.manage', $draw) : $workspace.'#groups'],
            ['label' => 'Competition format', 'complete' => $flags[2], 'detail' => $team ? 'Use the saved team tie format.' : (\App\Http\Controllers\Backend\DrawSetupController::OPTIONS[$draw->settings?->workflow][0] ?? ($format ? 'Existing draw format. Review it in the workspace.' : 'Choose how this draw will run.')), 'url' => $team ? route('draws.manage', $draw) : route('draw.setup.show', $draw)],
            ['label' => 'Match setup', 'complete' => $flags[3], 'detail' => $generated ? $playable->count().' playable matches generated. Review pairings and positions.' : ($team ? 'Review the selected teams and create their ties.' : 'Place players and generate the draw. Monrad supports 4, 8, 16, 32 or 64 positions.'), 'url' => $workspace],
            ['label' => 'Programme & schedule', 'complete' => $flags[4], 'detail' => $scheduled.' / '.$playable->count().' playable matches have a time, venue and court.', 'url' => route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'draw_ids' => [$draw->id]])],
            ['label' => 'Review & publish', 'complete' => $flags[5], 'detail' => $draw->oop_published && !$snapshotCurrent ? 'Saved match times differ from the published schedule. Review and publish the updates explicitly.' : 'Review the draw and match times, then publish each explicitly.', 'url' => $workspace],
        ];
        if ($competition) {
            $steps[1]['detail'] = $competition['group'].' · combined boys and girls regional sides.';
            $steps[2]['detail'] = ($competition['phase'] ?? null) === 'playoff' ? 'Placement playoffs from confirmed group standings.' : 'One round-robin group followed by 1st/2nd and 3rd/4th playoffs.';
            $steps[3]['detail'] .= ' Group and playoff match formats are saved separately.';
            $steps[4]['detail'] .= ($competition['phase'] ?? null) === 'round_robin' && !$linkedPlayoff ? ' Prepare a draft playoff programme to plan the placement matches before qualification.' : '';
        }
        if ($draw->isPlacementDraft()) {
            $steps[1]['complete'] = true;
            $steps[1]['detail'] = 'Round-robin positions are reserved; teams are filled after confirmed qualification.';
            $steps[3]['complete'] = $generated;
            $steps[5]['complete'] = false;
            $steps[5]['detail'] = 'Confirm qualifiers from the round-robin phase, then review lineups and publish separately.';
        }
        if ($draw->team_scoring_rules['undecided'] ?? false) {
            $warnings[] = 'Confirm the team scoring rules before publication. You can set up the programme and schedule first.';
            $steps[5]['complete'] = false;
        }
        $next = collect($steps)->search(fn ($step) => !$step['complete']);
        $complete = $next === false;
        return compact('steps', 'warnings', 'team', 'participants', 'generated', 'scheduleComplete', 'workspace', 'competition', 'linkedPlayoff', 'sourceDraw', 'complete') + ['next' => $next === false ? 5 : $next];
    }

    private function snapshotCurrent(Draw $draw): bool
    {
        $team = $draw->isTeamDraw();
        $kind = $team ? 'team' : 'individual';
        $working = $team
            ? $draw->fixtures()->whereNotNull('scheduled_at')->get()->map(fn ($fixture) => ['fixture_kind' => $kind, 'fixture_id' => $fixture->id, 'scheduled_at' => $fixture->scheduled_at, 'venue_id' => $fixture->venue_id, 'court' => $fixture->court_label, 'duration' => $fixture->duration_min])
            : $draw->drawFixtures()->whereHas('orderOfPlay', fn ($query) => $query->whereNotNull('time'))->with('orderOfPlay')->get()->map(fn ($fixture) => ['fixture_kind' => $kind, 'fixture_id' => $fixture->id, 'scheduled_at' => $fixture->orderOfPlay->time, 'venue_id' => $fixture->orderOfPlay->venue_id, 'court' => $fixture->orderOfPlay->court, 'duration' => $fixture->orderOfPlay->duration_minutes]);
        $published = DB::table('published_schedule_assignments')->where('event_id', $draw->event_id)->where('draw_id', $draw->id)->get()->map(fn ($row) => (array) $row);
        $normalize = fn ($rows) => $rows->mapWithKeys(fn ($row) => [$row['fixture_kind'].':'.$row['fixture_id'] => [
            Carbon::parse($row['scheduled_at'])->format('Y-m-d H:i:s'), (string) (int) $row['venue_id'], (string) $row['court'], (string) (int) ($row['duration'] ?: ($row['fixture_kind'] === 'team' ? 120 : 75)),
        ]])->sortKeys()->all();
        return $working->isNotEmpty() && $normalize($working) === $normalize($published);
    }
}
