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
        $fixtures = $draw->drawFixtures()->where('stage', 'RR')->with('fixtureResults')->get();
        $outcomes = app(IndividualMatchOutcomeService::class);
        $total = $fixtures->count();
        $completed = $fixtures->filter(function ($fixture) use ($draw, $outcomes) {
            $fixture->setRelation('draw', $draw);
            return $fixture->registration1_id && $fixture->registration2_id
                && ($outcomes->winner($fixture) !== null || $outcomes->isDraw($fixture));
        })->count();
        $remaining = $total - $completed;
        return [
            'ready' => $total > 0 && $remaining === 0,
            'message' => $total === 0 ? 'Create the round-robin fixtures before progressing this draw.'
                : "Complete all round-robin results first. {$remaining} of {$total} matches still need a result.",
            'round_robin' => compact('total', 'completed', 'remaining'),
        ];
    }
}
