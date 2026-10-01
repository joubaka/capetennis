<?php

namespace App\Services\InterprovincialTrials;

use App\Models\{CategoryEventRegistration, Draw, Fixture, FlexibleMonradDraw};

class TrialWithdrawalProgressionService
{
    public function resolve(CategoryEventRegistration $entry): void
    {
        $category = $entry->categoryEvent;
        foreach (Draw::where('event_id', $category->event_id)->where('category_event_id', $category->id)->get() as $draw) {
            $flexible = FlexibleMonradDraw::where('draw_id', $draw->id)->whereNotNull('graph')->first();
            if ($flexible) {
                app(\App\Services\Draw\FlexibleMonradService::class)->reconcileWithdrawals($draw, $flexible->revision);
                continue;
            }
            // Keep completed history and award each remaining reachable match as a loss.
            $withdrawn = $category->categoryEventRegistrations()->where('status', 'withdrawn')->pluck('registration_id')->map(fn ($id) => (int) $id);
            do {
                $progressed = false;
                $fixtures = Fixture::where('draw_id', $draw->id)->whereIn('match_status', [0, 2])
                    ->where(fn ($q) => $q->whereIn('registration1_id', $withdrawn)->orWhereIn('registration2_id', $withdrawn))->orderBy('round')->orderBy('id')->lockForUpdate()->get();
                foreach ($fixtures as $fixture) {
                    if (!$fixture->registration1_id || !$fixture->registration2_id) { continue; }
                    $firstOut = $withdrawn->contains((int) $fixture->registration1_id);
                    $secondOut = $withdrawn->contains((int) $fixture->registration2_id);
                    if ($firstOut && $secondOut) {
                        $fixture->update(['match_status' => 5, 'winner_registration' => null]);
                        if ($fixture->stage !== 'RR') {
                            app(\App\Domain\Fixtures\Services\FixtureProgressionService::class)->advance($fixture, 0, 0);
                        }
                    } else {
                        $winner = (int) ($firstOut ? $fixture->registration2_id : $fixture->registration1_id);
                        $loser = (int) ($firstOut ? $fixture->registration1_id : $fixture->registration2_id);
                        $fixture->update(['match_status' => 3, 'winner_registration' => $winner]);
                        if ($fixture->stage !== 'RR') {
                            app(\App\Domain\Fixtures\Services\FixtureProgressionService::class)->advance($fixture, $winner, $loser);
                        }
                    }
                    $progressed = true;
                    \App\Models\DrawAuditLog::record($draw->id, 'trials_withdrawal_loss', $fixture->id, ['registration_id' => $entry->registration_id]);
                }
            } while ($progressed);
            app(\App\Domain\Draws\Services\ByeAdvancementService::class)->advance($draw);
            $draw->registrations()->detach($entry->registration_id);
        }
        app(TrialRefreshQueue::class)->remember((int) $category->event_id, true, (int) $category->id);
    }
}
