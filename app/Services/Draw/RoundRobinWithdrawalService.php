<?php

namespace App\Services\Draw;

use App\Models\{Draw, DrawAuditLog, Fixture};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/** Competition-only withdrawal: registration, payments and saved scores stay intact. */
final class RoundRobinWithdrawalService
{
    public static function withdrawnIds(Collection $fixtures, int $groupId): Collection
    {
        return $fixtures->filter(fn ($fixture) => $fixture->stage === 'RR'
            && (int) $fixture->draw_group_id === $groupId && $fixture->isRoundRobinVoided())
            ->pluck('round_robin_voided_registration_id')->map(fn ($id) => (int) $id)->unique()->values();
    }

    public function revision(Fixture $fixture): string
    {
        return hash('sha256', json_encode($fixture->draw->drawFixtures()->where('stage', 'RR')
            ->where('draw_group_id', $fixture->draw_group_id)->with(['fixtureResults', 'draw.settings'])->orderBy('id')->get()
            ->map(fn ($match) => app(IndividualScoreRevision::class)->forFixture($match))->all()));
    }

    public function withdraw(Draw $draw, Fixture $fixture, string $revision): int
    {
        return app(IndividualScoreRevision::class)->locked($draw, $fixture, function (Draw $draw, Fixture $fixture) use ($revision) {
            Gate::authorize('voidRoundRobin', $draw);
            abort_if($draw->locked || $draw->isTeamDraw(), 403, 'Unlock an individual draw before withdrawing a round-robin player.');
            abort_unless($fixture->stage === 'RR' && $fixture->draw_group_id
                && $draw->groups()->whereKey($fixture->draw_group_id)->exists(), 422, 'Choose a round-robin match in this draw.');
            abort_unless($fixture->isRetired(), 422, 'Record the retired match first, then withdraw the retired player.');
            $registration = $fixture->retiredRegistrationId();
            $matches = $draw->drawFixtures()->where('stage', 'RR')->where('draw_group_id', $fixture->draw_group_id)
                ->where(fn ($query) => $query->where('registration1_id', $registration)->orWhere('registration2_id', $registration))
                ->lockForUpdate()->get();
            // A repeated confirmation must not create another audit or notification change.
            if ($matches->isNotEmpty() && $matches->every(fn ($match) => $match->isRoundRobinVoided())) return 0;
            abort_unless(hash_equals($this->revision($fixture), $revision), 409, 'The round-robin results changed. Reload and review the withdrawal again.');
            TwoStageRoundRobinService::assertScoreMutable($fixture);
            InterproRoundRobinFormat::assertScoreMutable($fixture);
            abort_if((int) $fixture->drawGroup?->round_robin_phase !== 2 && $draw->drawFixtures()->where('stage', '!=', 'RR')
                ->where(fn ($query) => $query->where('registration1_id', '>', 0)->orWhere('registration2_id', '>', 0)->orWhereHas('fixtureResults'))->exists(),
                409, 'Playoff players are already confirmed. Review progression before voiding round-robin results.');
            abort_unless($draw->groups()->findOrFail($fixture->draw_group_id)->groupRegistrations()->where('registration_id', $registration)->exists(),
                422, 'The retired player is not a member of this group.');
            $changed = [];
            foreach ($matches as $match) {
                if ($match->isRoundRobinVoided()) continue;
                $match->update(['round_robin_voided_registration_id' => $registration]);
                app(\App\Services\MatchResultNotificationService::class)->invalidate($match);
                $changed[] = $match->id;
            }
            app(TwoStageRoundRobinService::class)->reconcileVoidedSources($draw, $registration);
            DrawAuditLog::record($draw->id, 'round_robin_player_withdrawn', $fixture->id,
                ['draw_group_id' => $fixture->draw_group_id, 'registration_id' => $registration, 'voided_fixture_ids' => $changed]);
            return count($changed);
        });
    }
}
