<?php

namespace App\Services\Draw;

use App\Models\{Draw, DrawAuditLog, Event, FixtureResult, User};
use Illuminate\Support\Facades\{DB, Gate};

/** Refresh downstream names only after the original qualification was confirmed. */
final class InterproPlayoffRefreshService
{
    public function available(Draw $draw): bool
    {
        return InterproRoundRobinFormat::selected($draw)
            && !empty($draw->settings->playoff_config[0]['trials_confirmed']);
    }

    public function review(Draw $draw): array
    {
        $this->assertConfirmed($draw);
        return ['mode' => 'refresh', 'revision' => $this->revision($draw)];
    }

    public function refresh(Draw $draw, string $revision, User $actor): array
    {
        return DB::transaction(function () use ($draw, $revision, $actor) {
            Event::whereKey($draw->event_id)->lockForUpdate()->firstOrFail();
            $current = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $current->event_id === (int) $draw->event_id, 409, 'The draw changed. Reload before refreshing playoff names.');
            Gate::forUser($actor)->authorize('progress', $current);
            $current->settings()->lockForUpdate()->firstOrFail();
            $fixtures = $current->drawFixtures()->lockForUpdate()->get();
            FixtureResult::whereIn('fixture_id', $fixtures->pluck('id'))->lockForUpdate()->get();
            $groups = $current->groups()->lockForUpdate()->get();
            DB::table('draw_group_registrations')->whereIn('draw_group_id', $groups->pluck('id'))->lockForUpdate()->get();
            $this->assertConfirmed($current);
            abort_unless(strlen($revision) === 64 && hash_equals($this->revision($current), $revision), 409,
                'The draw or results changed. Refresh the page before updating playoff names.');

            $format = app(InterproRoundRobinFormat::class);
            $format->assertGroupsReady($current);
            $beforeConfig = $current->settings->playoff_config;
            $resolved = $format->resolve($current, confirm: false);
            // The canonical resolver also persists valid first-playoff carries/skips.
            // It rejects changed occupied slots rather than replacing qualifiers.
            if ($resolved > 0 || $beforeConfig !== $current->fresh()->settings->playoff_config) {
                DrawAuditLog::record($current->id, 'interpro_playoff_names_refreshed', null,
                    ['resolved_slots' => $resolved, 'review_revision' => $revision]);
            }
            return ['resolved' => $resolved];
        });
    }

    private function assertConfirmed(Draw $draw): void
    {
        abort_unless($this->available($draw) && $draw->event?->isInterprovincialTrials() && !$draw->isTeamDraw(), 409,
            'Confirm the Interpro round-robin qualification before refreshing playoff names.');
        abort_if($draw->locked, 403, 'Unlock this draw before refreshing playoff names.');
        $fixtures = $draw->drawFixtures()->where('stage', '!=', 'RR')->with('fixtureResults')->get();
        $template = InterproRoundRobinFormat::template($draw);
        $byNumber = $fixtures->keyBy('match_nr');
        $groups = $draw->groups()->get()->keyBy('name');
        abort_unless($groups->keys()->sort()->values()->all() === ['A', 'B', 'C', 'D']
            && $fixtures->count() === count($template)
            && $byNumber->keys()->sort()->values()->all() === collect(array_keys($template))->sort()->values()->all(),
            409, 'Review the saved Interpro playoff structure before refreshing names.');
        foreach ($template as $number => $definition) {
            $fixture = $byNumber[$number];
            $fixture->setRelation('draw', $draw);
            $hasOutcome = $fixture->fixtureResults->isNotEmpty() || $fixture->winner_registration
                || in_array((int) $fixture->match_status, [1, 3, 4], true);
            abort_if($hasOutcome && (!$fixture->registration1_id || !$fixture->registration2_id)
                && !InterproRoundRobinFormat::automaticWinner($fixture),
                409, 'A recorded playoff result has missing participants. Review draw recovery before refreshing names.');
            abort_unless($fixture->stage === $definition[0] && (int) $fixture->round === $definition[1]
                && (int) $fixture->position === (int) ($definition[3] ?? null), 409, 'The saved playoff structure changed. Review draw recovery.');
            foreach ($definition[2] as $slot => $source) {
                if (preg_match('/^([A-D])([1-3])$/', $source, $match)) {
                    abort_unless($fixture->{'registration'.($slot + 1).'_id'}
                        && (int) $fixture->{'registration'.($slot + 1).'_source_group_id'} === (int) $groups[$match[1]]->id
                        && (int) $fixture->{'registration'.($slot + 1).'_source_position'} === (int) $match[2], 409,
                        'The confirmed qualifying slots need review. Refresh cannot qualify players again.');
                } elseif (preg_match('/^([WL])(\d+)$/', $source, $match)) {
                    $field = $match[1] === 'W' ? 'parent_fixture_id' : 'loser_parent_fixture_id';
                    abort_unless((int) $byNumber[(int) $match[2]]->{$field} === (int) $fixture->id, 409,
                        'The saved playoff routes changed. Review draw recovery.');
                }
            }
        }
    }

    private function revision(Draw $draw): string
    {
        $draw->load('settings');
        $groups = $draw->groups()->with('groupRegistrations')->orderBy('id')->get();
        return hash('sha256', serialize([
            app(InterproChallengeSelectionService::class)->revision($draw),
            $groups->map(fn ($group) => [$group->getAttributes(), $group->groupRegistrations->map->getAttributes()->all()])->all(),
        ]));
    }
}
