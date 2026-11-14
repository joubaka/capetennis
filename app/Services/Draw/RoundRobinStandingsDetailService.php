<?php

namespace App\Services\Draw;

use App\Models\Draw;
use App\Services\IndividualMatchOutcomeService;

/** Read-only match evidence for each group's standings, including carried results. */
final class RoundRobinStandingsDetailService
{
    public function annotate(Draw $draw, array $standings): array
    {
        $draw->loadMissing(['settings', 'groups.groupRegistrations.registration.players', 'drawFixtures.fixtureResults']);
        $fixtures = HybridRoundRobinService::effectiveFixtures($draw);
        $outcomes = app(IndividualMatchOutcomeService::class);
        foreach ($draw->groups as $group) {
            $members = $group->groupRegistrations->keyBy('registration_id');
            $groupFixtures = $fixtures->filter(fn ($fixture) => $fixture->stage === 'RR'
                && (int) $fixture->draw_id === (int) $draw->id && (int) $fixture->draw_group_id === (int) $group->id
                && $members->has($fixture->registration1_id) && $members->has($fixture->registration2_id))
                ->unique('id')->values();
            foreach ($standings[$group->id] ?? [] as $key => $row) {
                $id = (int) $row['reg_id'];
                $details = [];
                foreach ($members as $opponentId => $member) {
                    if ((int) $opponentId === $id) continue;
                    $fixture = $groupFixtures->first(fn ($fixture) =>
                        ((int) $fixture->registration1_id === $id && (int) $fixture->registration2_id === (int) $opponentId)
                        || ((int) $fixture->registration2_id === $id && (int) $fixture->registration1_id === (int) $opponentId));
                    $status = 'pending';
                    $score = '';
                    $carried = false;
                    $ending = null;
                    if ($fixture) {
                        $fixture->setRelation('draw', $draw);
                        $winner = $outcomes->winner($fixture);
                        $drawn = ! $winner && $outcomes->isDraw($fixture);
                        $status = $winner ? ((int) $winner === $id ? 'won' : 'lost') : ($drawn ? 'drawn' : ($fixture->fixtureResults->isNotEmpty() ? 'incomplete' : 'pending'));
                        $home = (int) $fixture->registration1_id === $id;
                        $score = $fixture->fixtureResults->sortBy('set_nr')->unique('set_nr')->map(fn ($set) =>
                            $home ? "{$set->registration1_score}–{$set->registration2_score}" : "{$set->registration2_score}–{$set->registration1_score}")->implode(', ');
                        $carried = $fixture->isDirty('draw_group_id');
                        $ending = $fixture->isWalkover() ? 'Walkover' : ($fixture->isRetired() ? 'Retirement' : null);
                    }
                    $details[] = ['fixture_id' => $fixture?->id, 'opponent' => $member->registration?->display_name ?? 'Unknown player',
                        'status' => $status, 'score' => $score, 'carried' => $carried, 'ending' => $ending];
                }
                $played = count(array_filter($details, fn ($match) => in_array($match['status'], ['won', 'lost', 'drawn'], true)));
                $standings[$group->id][$key]['played'] = $played;
                $standings[$group->id][$key]['draws'] ??= 0;
                $standings[$group->id][$key]['points'] ??= 2 * (int) $row['wins'] + (int) ($row['draws'] ?? 0);
                $standings[$group->id][$key]['remaining'] = max(0, $members->count() - 1 - $played);
                $standings[$group->id][$key]['match_details'] = $details;
                $standings[$group->id][$key]['incomplete_results'] = count(array_filter($details, fn ($match) => $match['status'] === 'incomplete'));
            }
        }
        return $standings;
    }
}
