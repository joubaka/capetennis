<?php

namespace App\Services\Draw;

use App\Models\Draw;
use Illuminate\Validation\ValidationException;

/** Presentation only: navigation must never reopen, generate or publish a draw. */
final class DrawWorkflow
{
    public function forDraw(Draw $draw): array
    {
        $date = request('schedule_date', request('date'));
        $context = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? ['schedule_date' => $date] : [];
        $workspace = route('backend.draw.roundrobin.show', [$draw] + $context);
        $publicationUrl = route('backend.draw.roundrobin.show', [$draw] + $context + ['review' => 'publication']).'#matrix';
        $resultsUrl = route('backend.draw.roundrobin.show', [$draw] + $context + ['view' => 'results']).'#matrix';
        $flexible = $draw->usesFlexibleMonrad();
        $record = $flexible ? $draw->flexibleMonrad : null;
        $fixtures = $draw->drawFixtures()->with('orderOfPlay')->withCount('fixtureResults')->get();
        $playable = $fixtures->reject(fn ($fixture) => $fixture->winner_registration && (!$fixture->registration1_id || !$fixture->registration2_id));
        $generated = $playable->isNotEmpty();
        $results = $fixtures->sum('fixture_results_count');
        $scheduled = $playable->filter(fn ($fixture) => $fixture->orderOfPlay?->time && $fixture->orderOfPlay?->court && $fixture->orderOfPlay?->venue_id)->count();
        $playersReady = false;
        if ($flexible && $record?->draft) {
            try {
                $graph = app(FlexibleMonradCompiler::class)->compile($record->draft);
                $active = app(FlexibleMonradService::class)->activeRegistrationIds($draw, $graph['players']);
                $playersReady = count(array_diff($graph['players'], $active)) === 0;
            } catch (ValidationException|\TypeError $exception) {
                // A partial saved draft is valid work in progress, not a completed step.
            }
        } elseif (!$flexible) {
            $groups = $draw->groups()->where('round_robin_phase', 1)->with('groupRegistrations')->get();
            $eligible = app(GroupAssignmentService::class)->eligible($draw)->pluck('registration_id')->unique()->sort()->values()->all();
            $assigned = $groups->flatMap(fn ($group) => $group->groupRegistrations->pluck('registration_id'));
            $playersReady = $groups->isNotEmpty() && $groups->every(fn ($group) => $group->groupRegistrations->count() >= 2)
                && $assigned->count() === $assigned->unique()->count() && $assigned->unique()->sort()->values()->all() === $eligible;
            if (app(GroupAssignmentService::class)->fixturesNeedRegeneration($draw)) $generated = false;
        }
        if ($flexible) {
            $generated = $generated && is_array($record?->graph) && is_array($record?->fixture_map)
                && is_array($record->graph['nodes'] ?? null) && is_array($record->graph['positions'] ?? null)
                && count($record->fixture_map) === $fixtures->count()
                && !array_diff(array_keys($record->graph['nodes']), array_keys($record->fixture_map))
                && !array_diff(array_keys($record->fixture_map), array_keys($record->graph['nodes']));
            if ($generated) {
                try {
                    $input = [];
                    $byId = $fixtures->keyBy('id');
                    foreach ($record->fixture_map as $key => $id) {
                        $fixture = $byId->get($id);
                        if (!$fixture) throw new \LogicException('Missing fixture');
                        $input[$key] = ['players' => [$fixture->registration1_id ? (int) $fixture->registration1_id : null, $fixture->registration2_id ? (int) $fixture->registration2_id : null], 'winner' => $fixture->winner_registration ? (int) $fixture->winner_registration : null, 'played' => $fixture->fixture_results_count > 0];
                    }
                    $progression = app(FlexibleMonradProgression::class)->resolve($record->graph, $input, $record->graph['withdrawn'] ?? []);
                    $ids = collect($progression['matches'])->reject(fn ($match) => $match['automatic'])->keys()->map(fn ($key) => $record->fixture_map[$key]);
                    $playable = $fixtures->whereIn('id', $ids);
                    $scheduled = $playable->filter(fn ($fixture) => $fixture->orderOfPlay?->time && $fixture->orderOfPlay?->court && $fixture->orderOfPlay?->venue_id)->count();
                } catch (\LogicException|\TypeError|\ErrorException $exception) { $generated = false; }
            }
        }
        $format = (bool) $draw->settings?->workflow;
        $rulesReady = $flexible ? $format : ($format && filled($draw->settings?->score_format));
        $scheduleReady = $generated && $scheduled === $playable->count();
        $publicationReady = $scheduleReady && $draw->published && $draw->oop_published && app(DrawSetupGuide::class)->snapshotCurrent($draw);
        $resultsComplete = $generated && ($flexible ? ($progression['positions_complete'] ?? false)
            : $playable->every(fn ($fixture) => (bool) $fixture->winner_registration));
        if ($draw->isTwoStageRoundRobin() && !$draw->groups()->where('round_robin_phase', 2)->exists()) $resultsComplete = false;
        if ($draw->settings?->workflow === 'round_robin_playoffs') $resultsComplete = false; // Qualification can have optional, not-yet-created branches.
        // Publication remains a separate, explicit review, including saved/public differences.
        $steps = [
            'setup' => ['label' => 'Setup', 'url' => $format ? $workspace.'#settings' : route('draw.setup.show', $draw), 'complete' => $rulesReady],
            'players' => ['label' => 'Players', 'url' => $workspace.'#groups', 'complete' => $playersReady],
            'matches' => ['label' => 'Matches', 'url' => $workspace.'#matrix', 'complete' => $generated],
            'schedule' => ['label' => 'Schedule', 'url' => $workspace.'#schedule', 'complete' => $scheduleReady],
            'review' => ['label' => 'Review & publish', 'url' => $publicationUrl, 'complete' => $publicationReady],
            'results' => ['label' => 'Results', 'url' => $resultsUrl, 'complete' => $resultsComplete],
        ];
        $next = !$rulesReady ? 'setup' : (!$playersReady ? 'players' : (!$generated ? 'matches' : (!$scheduleReady ? 'schedule' : (!$publicationReady ? 'review' : 'results'))));
        $blockers = [];
        if ($draw->locked) $blockers[] = 'This draw is locked. Use More tools → Unlock draw before changing protected setup.';
        if ($draw->published) $blockers[] = 'The draw is public. Hide the draw through Publish before rebuilding its structure.';
        if ($draw->oop_published) $blockers[] = 'Match times are published. Review and hide the affected times before a structural reset.';
        if ($draw->event->hasRecordedResults()) $blockers[] = 'Results have been recorded in this event. Competition format and group rules cannot be changed.';
        return compact('steps', 'next', 'blockers', 'generated', 'playersReady', 'scheduled', 'results', 'workspace', 'resultsComplete') + [
            'context' => $context,
            'format' => \App\Http\Controllers\Backend\DrawSetupController::OPTIONS[$draw->settings?->workflow][0] ?? 'Choose a format',
            'matches' => $fixtures->count(),
        ];
    }
}
