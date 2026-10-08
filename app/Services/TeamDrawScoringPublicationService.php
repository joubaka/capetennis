<?php

namespace App\Services;

use App\Models\Draw;
use App\Models\DrawAuditLog;
use App\Models\TeamFixturePlayer;
use App\Models\TeamTie;

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
            'ready' => $canonical ? $total > 0 && $pending === 0 && ! $orphaned : $draw->fixtures()->exists(),
            'pending' => $pending,
            'total' => $total,
        ];
    }

    public function enableLocked(Draw $draw): array
    {
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
