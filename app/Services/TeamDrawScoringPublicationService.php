<?php

namespace App\Services;

use App\Domain\Draws\Enums\FixtureState;
use App\Models\Draw;
use App\Models\DrawAuditLog;
use App\Models\Fixture;
use App\Models\TeamFixture;
use App\Models\TeamFixturePlayer;
use App\Models\TeamTie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Called only while the publication service holds the event and draw locks. */
final class TeamDrawScoringPublicationService
{
    public function state(Draw $draw): array
    {
        $total = $draw->teamTies()->count();
        $canonical = $total > 0 || $draw->team_format_snapshot !== null || $draw->team_scoring_rules !== null;
        $pending = $draw->teamTies()->where(fn ($query) => $query
            ->whereNotIn('status', [TeamTie::STATUS_PUBLISHED, TeamTie::STATUS_COMPLETED])
            ->orWhereNull('published_at'))->count();
        $orphaned = $canonical && $draw->fixtures()->where(fn ($query) => $query->whereNull('team_tie_id')
            ->orWhereDoesntHave('teamTie', fn ($tie) => $tie->where('draw_id', $draw->id)))->exists();

        return [
            'canonical' => $canonical,
            'ready' => !$draw->isPlacementDraft() && !($draw->team_scoring_rules['undecided'] ?? false) && ($canonical ? $total > 0 && $pending === 0 && ! $orphaned : $draw->fixtures()->exists()),
            'can_disable' => !$draw->published && !$draw->locked && empty($draw->team_draw_selection['competition']) && $total > 0 && $draw->teamTies()
                ->where(fn ($query) => $query->where('status', TeamTie::STATUS_PUBLISHED)->orWhereNotNull('published_at'))->exists(),
            'pending' => $pending,
            'total' => $total,
        ];
    }

    public function disable(Draw $draw): void
    {
        DB::transaction(function () use ($draw) {
            app(TeamDrawAdaptationService::class)->lockEvent($draw->event_id);
            $current = Draw::query()->lockForUpdate()->findOrFail($draw->id);
            abort_unless($current->event_id === $draw->event_id, 409, 'Draw event changed. Refresh before disabling scoring.');
            Gate::authorize('publish', $current);
            abort_if(!$current->isTeamDraw() || $current->published || $current->locked, 422,
                'Only a hidden, unlocked team draw can have scoring disabled.');
            abort_if(!empty($current->team_draw_selection['competition']), 422, 'Scoring cannot be disabled for a linked placement programme.');

            $ties = $current->teamTies()->orderBy('id')->lockForUpdate()->get();
            $fixtures = $current->fixtures()->lockForUpdate()->get();
            $enabled = $ties->filter(fn ($tie) => $tie->status === TeamTie::STATUS_PUBLISHED || $tie->published_at);
            if ($enabled->isEmpty()) return;

            abort_if($fixtures->contains(fn ($fixture) => !$ties->contains('id', $fixture->team_tie_id))
                || TeamFixture::whereIn('team_tie_id', $ties->modelKeys())
                    ->where(fn ($query) => $query->where('draw_id', '!=', $current->id)->orWhereNull('draw_id'))->exists()
                || Fixture::where('draw_id', $current->id)->exists(), 422, 'Review inconsistent match and tie assignments before disabling scoring.');
            abort_if($ties->contains(fn ($tie) => !in_array($tie->status, [TeamTie::STATUS_DRAFT, TeamTie::STATUS_VALIDATED, TeamTie::STATUS_PUBLISHED], true) || $tie->winner_team_id)
                || $fixtures->contains(fn ($fixture) => (int) $fixture->match_status !== FixtureState::STATUS_PENDING)
                || DB::table('team_fixture_results')->whereIn('team_fixture_id', $fixtures->modelKeys())->lockForUpdate()->first(['id'])
                || DrawAuditLog::where('draw_id', $current->id)->whereIn('action', ['match_started', 'match_stopped', 'score_saved', 'score_corrected', 'score_deleted', 'team_scores_cleared'])
                    ->lockForUpdate()->first(['id']),
                422, 'Scoring cannot be disabled because this draw has started play or has score history.');

            foreach ($enabled as $tie) {
                $tie->update(['status' => TeamTie::STATUS_VALIDATED, 'published_at' => null]);
            }
            DrawAuditLog::record($current->id, 'scoring_disabled', null, ['tie_ids' => $enabled->modelKeys()]);
        });
    }

    public function blocksScoring(Draw $draw, ?TeamTie $tie, bool $locked = false): bool
    {
        if ($tie?->published_at && in_array($tie->status, [TeamTie::STATUS_PUBLISHED, TeamTie::STATUS_COMPLETED], true)) {
            return false;
        }

        // Preserve scoring on older tied draws unless an administrator explicitly disabled it.
        return $draw->team_scoring_rules !== null || ($tie && DrawAuditLog::where('draw_id', $draw->id)
            ->where('action', 'scoring_disabled')->when($locked, fn ($query) => $query->lockForUpdate())->first(['id']) !== null);
    }

    public function enableLocked(Draw $draw): array
    {
        if ($draw->isPlacementDraft()) throw new \RuntimeException('Confirm playoff qualifiers before enabling scoring.');
        if ($draw->team_scoring_rules['undecided'] ?? false) throw new \RuntimeException('Scoring is to be decided. Confirm the phase scoring rules first.');
        $ties = $draw->teamTies()->orderBy('id')->lockForUpdate()->get();
        if ($ties->isEmpty()) {
            if ($draw->team_format_snapshot !== null || $draw->team_scoring_rules !== null) {
                throw new \RuntimeException('Generate team ties before enabling scoring.');
            }
            if (! $draw->fixtures()->exists()) {
                throw new \RuntimeException('Generate team matches before enabling scoring.');
            }
            // Legacy team fixtures do not use the operational tie scoring gate.
            return [];
        }

        $fixtures = $draw->fixtures()->orderBy('id')->lockForUpdate()->get();
        TeamFixturePlayer::whereIn('team_fixture_id', $fixtures->pluck('id'))->orderBy('id')->lockForUpdate()->get();
        if ($fixtures->contains(fn ($fixture) => ! $ties->contains('id', $fixture->team_tie_id))) {
            throw new \RuntimeException('Every team match must belong to a tie in this draw. Review team ties.');
        }

        $changed = [];
        foreach ($ties as $tie) {
            $tie->setRelation('draw', $draw);
            if ($tie->rubbers()->where('draw_id', '!=', $draw->id)->exists()) {
                throw new \RuntimeException("Tie #{$tie->id} contains a match from another draw.");
            }
            $ready = $tie->published_at && in_array($tie->status, [TeamTie::STATUS_PUBLISHED, TeamTie::STATUS_COMPLETED], true);
            $played = $tie->isCompleted() || $tie->winner_team_id || $tie->rubbers()->where(fn ($query) => $query
                ->where('match_status', '!=', 0)->orWhereHas('fixtureResults'))->exists();
            if (! $ready && ($draw->locked || $tie->isLocked() || $tie->published_at || $played)) {
                throw new \RuntimeException("Tie #{$tie->id} is protected or already played. Review its scoring state before enabling scoring.");
            }
            if (! $ready || ! $played) {
                if ($tie->home_team_id === $tie->away_team_id
                    || ! $tie->homeTeam?->category || ! $tie->awayTeam?->category
                    || (int) $tie->homeTeam->category->event_id !== (int) $draw->event_id
                    || (int) $tie->awayTeam->category->event_id !== (int) $draw->event_id
                    || $draw->teams_in_draw()->whereIn('teams.id', [$tie->home_team_id, $tie->away_team_id])->count() !== 2) {
                    throw new \RuntimeException("Tie #{$tie->id} teams must be distinct participants in this draw and event.");
                }
                $snapshot = $tie->format_snapshot ?? $draw->team_format_snapshot;
                if (! is_array($snapshot) || empty($snapshot['rubbers'])
                    || ! isset($snapshot['min_roster_size'], $snapshot['max_roster_size'])) {
                    throw new \RuntimeException("Tie #{$tie->id} has no format snapshot. Review team ties before publishing.");
                }
                try {
                    app(TeamTieValidationService::class)->assertTieComplete($tie);
                } catch (\InvalidArgumentException $exception) {
                    throw new \RuntimeException("Tie #{$tie->id}: ".$exception->getMessage(), 0, $exception);
                }
            }
            if (! $ready) {
                $tie->update(['status' => TeamTie::STATUS_PUBLISHED, 'published_at' => now()]);
                $changed[] = $tie->id;
            }
        }
        if ($changed) {
            DrawAuditLog::record($draw->id, 'scoring_enabled', null, ['tie_ids' => $changed]);
        }

        return $changed;
    }
}
