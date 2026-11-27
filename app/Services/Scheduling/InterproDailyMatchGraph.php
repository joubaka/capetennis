<?php

namespace App\Services\Scheduling;

use App\Models\Draw;
use App\Services\Draw\InterproRoundRobinFormat;
use App\Services\IndividualMatchOutcomeService;
use Illuminate\Support\Collection;

/** Read-only conditional entrants for the canonical Interpro playoff topology. */
final class InterproDailyMatchGraph
{
    public static function build(Draw $draw, Collection $fixtures): array
    {
        $playoffs = $fixtures->where('stage', '!=', 'RR');
        if ($playoffs->isEmpty()) return [];
        $template = InterproRoundRobinFormat::template($draw);
        if ($playoffs->count() !== count($template)
            || $playoffs->pluck('match_nr')->map(fn ($number) => (int) $number)->sort()->values()->all() !== collect(array_keys($template))->sort()->values()->all()) {
            throw new \InvalidArgumentException('Review the complete Interpro playoff fixtures before using the daily player limit.');
        }
        $groups = $draw->groups()->with('groupRegistrations')->get();
        if ($groups->pluck('name')->sort()->values()->all() !== ['A', 'B', 'C', 'D']
            || $groups->contains(fn ($group) => ! in_array($group->groupRegistrations->count(), [3, 4], true))) {
            throw new \InvalidArgumentException('Review the four Interpro groups before using the daily player limit.');
        }
        $allMembers = $groups->flatMap(fn ($group) => $group->groupRegistrations->pluck('registration_id'));
        if ($allMembers->count() !== $allMembers->unique()->count()) {
            throw new \InvalidArgumentException('Review duplicate Interpro group membership before using the daily player limit.');
        }
        $matches = []; $possible = [];
        foreach ($groups as $group) {
            $ids = $group->groupRegistrations->pluck('registration_id')->map(fn ($id) => (int) $id)->filter()->unique()->values();
            if ($ids->count() !== $group->groupRegistrations->count()) {
                throw new \InvalidArgumentException('Review Interpro group membership before using the daily player limit.');
            }
            // A qualifier is not yet known: any member may occupy that group's qualifying rank.
            $matches['group:'.$group->name] = ['id' => 'interpro-group:'.$group->id, 'players' => [], 'winner' => null,
                'automatic' => 'qualifying_candidates', 'sources' => $ids->map(fn ($id) => ['type' => 'player', 'id' => $id])->all()];
            $possible['group:'.$group->name] = $ids->all();
        }
        $byNumber = $playoffs->keyBy('match_nr');
        foreach ($template as $number => [$stage, $round, $sources]) {
            $fixture = $byNumber[$number];
            if ($fixture->stage !== $stage || (int) $fixture->draw_id !== (int) $draw->id) {
                throw new \InvalidArgumentException('Review the Interpro playoff topology before using the daily player limit.');
            }
            $mapped = [];
            foreach ($sources as $slot => $source) {
                if (preg_match('/^([WL])(\d+)$/', $source, $parts)) {
                    $mapped[] = ['type' => $parts[1] === 'W' ? 'winner' : 'loser', 'match' => (int) $parts[2]];
                } else {
                    $group = $groups->firstWhere('name', $source[0]);
                    $actualGroup = $fixture->{'registration'.($slot + 1).'_source_group_id'};
                    $actualPosition = $fixture->{'registration'.($slot + 1).'_source_position'};
                    if ((int) $actualGroup !== (int) $group->id || (int) $actualPosition !== (int) $source[1]) {
                        throw new \InvalidArgumentException('Review Interpro qualifier sources before using the daily player limit.');
                    }
                    $participant = $fixture->{'registration'.($slot + 1).'_id'};
                    $key = $fixture->id.':'.($slot + 1);
                    if (isset($draw->team_draw_selection['playoff_lucky_losers'][$key])
                        || isset($draw->team_draw_selection['interpro_one_off_1654_places'][$key])) {
                        try {
                            $original = \App\Services\Draw\InterproOneOffPlayoffCorrection::rootSource($draw, $fixture, $slot + 1);
                            $effective = \App\Services\Draw\PlayoffLuckyLoserService::sourceOverride($draw, $fixture, $slot + 1, $original);
                        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
                            throw new \InvalidArgumentException('Review the original Interpro playoff replacement or position change.', previous: $exception);
                        }
                        if ((int) $participant !== $effective) {
                            throw new \InvalidArgumentException('Review the effective Interpro playoff participant.');
                        }
                        $mapped[] = ['type' => 'player', 'id' => $effective];
                        continue;
                    }
                    if ($participant && ! $group->groupRegistrations->contains('registration_id', $participant)) {
                        throw new \InvalidArgumentException('Review Interpro qualifier membership before using the daily player limit.');
                    }
                    $mapped[] = ['type' => 'winner', 'match' => 'group:'.$group->name];
                }
            }
            $fixture->setRelation('draw', $draw);
            $entrants = [];
            foreach ($mapped as $slot => $source) {
                $prior = $source['type'] === 'player' ? null : $matches[$source['match']];
                $candidates = $source['type'] === 'player' ? [$source['id']] : $possible[$source['match']];
                if ($prior && $prior['winner']) {
                    $candidates = $source['type'] === 'winner' ? [$prior['winner']] : array_values(array_diff($candidates, [$prior['winner']]));
                }
                $participant = $fixture->{'registration'.($slot + 1).'_id'};
                if ($participant) {
                    if (! in_array((int) $participant, $candidates, true)) {
                        throw new \InvalidArgumentException('Review Interpro playoff participants before using the daily player limit.');
                    }
                    $candidates = [(int) $participant];
                }
                $entrants = array_merge($entrants, $candidates);
            }
            $possible[$number] = array_values(array_unique($entrants));
            if (InterproRoundRobinFormat::hasReference($fixture) && ! InterproRoundRobinFormat::source($fixture)) {
                throw new \InvalidArgumentException('Review the original carried Interpro result before using the daily player limit.');
            }
            $matches[$number] = ['id' => $fixture->id, 'sources' => $mapped,
                'players' => [$fixture->registration1_id, $fixture->registration2_id],
                'winner' => app(IndividualMatchOutcomeService::class)->winner($fixture),
                // Keep feeder ancestry even when this result is carried: it adds no new appearance.
                'automatic' => InterproRoundRobinFormat::noMatchRequired($fixture)];
        }
        return $matches;
    }
}
