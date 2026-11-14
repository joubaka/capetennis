<?php

namespace App\Services\Draw;

use App\Models\Draw;
use App\Services\IndividualMatchOutcomeService;

/** Shared, read-only completion evidence for review, prompts and scoring pages. */
final class RoundRobinProgressReadiness
{
    public function forDraw(Draw $draw): array
    {
        $draw->loadMissing('settings');
        $staged = $draw->isTwoStageRoundRobin();
        $kind = $draw->hasHybridRoundRobin() ? 'hybrid' : ($staged ? 'second_round_robin' : 'playoffs');
        $destination = $kind === 'hybrid' ? 'top-four playoffs and the lower round robin'
            : ($staged ? 'second round robin' : 'playoffs');
        $fixtures = $draw->drawFixtures()->where('stage', 'RR')
            ->when($staged, fn ($query) => $query->whereHas('drawGroup', fn ($group) => $group->where('draw_id', $draw->id)->where('round_robin_phase', 1)))
            ->with('fixtureResults')->get();
        $outcomes = app(IndividualMatchOutcomeService::class);
        $total = $fixtures->count();
        $completed = $fixtures->filter(function ($fixture) use ($draw, $outcomes) {
            $fixture->setRelation('draw', $draw);
            return $fixture->registration1_id && $fixture->registration2_id
                && ($outcomes->winner($fixture) !== null || $outcomes->isDraw($fixture));
        })->count();
        $remaining = $total - $completed;
        if ($staged) {
            $pending = !$draw->hasConfirmedSecondRoundRobin();
        } else {
            $playoffs = $draw->drawFixtures()->where('stage', '!=', 'RR')->get();
            $pending = $playoffs->isEmpty() || !$playoffs->contains(fn ($fixture) => $fixture->registration1_id || $fixture->registration2_id)
                || $playoffs->contains(fn ($fixture) => ($fixture->registration1_source_group_id && !$fixture->registration1_id)
                    || ($fixture->registration2_source_group_id && !$fixture->registration2_id));
        }
        $ready = $total > 0 && $remaining === 0;
        $message = $total === 0 ? 'Create the round-robin fixtures before progressing this draw.'
            : "Complete all ".($staged ? 'first-stage ' : '')."round-robin results first. {$remaining} of {$total} matches still need a result.";
        // Staged qualification also requires valid membership, a full first-stage
        // programme and resolved positions. Prepared second-stage rows are ignored.
        if ($staged && $pending && $ready) {
            try {
                app(TwoStageRoundRobinService::class)->review($draw);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $ready = false;
                $message = $exception->getMessage();
            }
        }
        return [
            'ready' => $ready,
            'pending' => $pending,
            'kind' => $kind,
            'destination' => $destination,
            'message' => $message,
            'round_robin' => compact('total', 'completed', 'remaining'),
        ];
    }
}
