<?php

namespace App\Services;

use App\Models\{Draw, DrawAuditLog, TeamTie};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Organiser rulings supplement results; they never rewrite the score ledger. */
final class TeamCompetitionDecisionService
{
    public function fingerprint(Draw $draw, ?int $tieId = null): string
    {
        $ties = $draw->teamTies()->when($tieId, fn ($q) => $q->whereKey($tieId))->orderBy('id')->with(['rubbers' => fn ($q) => $q->orderBy('id'), 'rubbers.teamResults' => fn ($q) => $q->orderBy('id'), 'rubbers.fixturePlayers'])->get();
        $selection = $draw->team_draw_selection ?? [];
        unset($selection['decisions']);
        unset($selection['competition']['playoff_draw_id']);
        unset($selection['competition']['draft_playoff_draw_id']);
        $relevantIds = $tieId ? $ties->flatMap(fn ($tie) => [$tie->home_team_id, $tie->away_team_id])->all() : null;
        $rosterDraw = clone $draw;
        if ($relevantIds && !empty($selection['mixed_sides'])) {
            $selection['mixed_sides'] = array_intersect_key($selection['mixed_sides'], array_flip($relevantIds));
            $rosterDraw->team_draw_selection = $selection;
        }
        $sides = app(TeamDrawSideResolver::class)->teams($rosterDraw)->filter(fn ($team) => $relevantIds === null || in_array((int) $team->id, $relevantIds, true))->map(fn ($team) => [$team->id,
            $team->team_players->map(fn ($p) => [$p->player_id, $p->rank])->all(),
            $team->team_players_no_profile->map(fn ($p) => [$p->id, $p->rank])->all()])->all();
        return hash('sha256', json_encode([$selection, $draw->team_scoring_rules, $draw->team_format_snapshot, $sides,
            $ties->map(fn ($tie) => [$tie->id, $tie->home_team_id, $tie->away_team_id, $tie->format_snapshot,
                $tie->rubbers->map(fn ($rubber) => [$rubber->id, $rubber->rubber_code, $rubber->rubber_sequence, app(TeamParticipantHistoryService::class)->revision($rubber),
                    $rubber->teamResults->map(fn ($r) => [$r->id, $r->set_nr, $r->team1_score, $r->team2_score])->all()])->all()])->all()], JSON_THROW_ON_ERROR));
    }

    public function token(Draw $draw): string
    {
        return hash('sha256', $this->fingerprint($draw).json_encode($draw->team_draw_selection['decisions'] ?? []));
    }

    public function standingsDecision(Draw $draw): ?array
    {
        $decision = $draw->team_draw_selection['decisions']['standings'] ?? null;
        if (!$decision) return null;
        $decision['valid'] = !empty($draw->team_draw_selection['competition']['playoff_draw_id']) || hash_equals($decision['fingerprint'], $this->fingerprint($draw));
        return $decision;
    }

    public function applyStandings(Draw $draw, array $rows): array
    {
        $decision = $this->standingsDecision($draw);
        if (!$decision || !$decision['valid']) return $rows;
        $byId = collect($rows)->keyBy('team_id');
        $ordered = [];
        foreach ($decision['order'] as $index => $id) {
            if (!$byId->has($id)) continue;
            $row = $byId[$id];
            $row['rank'] = $index + 1;
            $row['qualification_excluded'] = in_array($id, $decision['excluded'], true);
            $ordered[] = $row;
        }
        return $ordered;
    }

    public function saveStandings(Draw $draw, array $input): void
    {
        $this->mutate($draw, $input, function (Draw $draw) use ($input) {
            $this->require(($draw->team_draw_selection['competition']['phase'] ?? null) === 'round_robin', 'Choose a round-robin competition.');
            $this->require(empty($draw->team_draw_selection['competition']['playoff_draw_id']), 'Playoffs already exist. Their qualified positions are frozen; do not change source standings.');
            $teams = app(TeamDrawSideResolver::class)->teams($draw);
            $ids = $teams->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            foreach ($teams as $team) $this->require((int) $team->category?->event_id === (int) $draw->event_id, 'A selected side belongs to another event.');
            $order = array_map('intval', $input['order']);
            $sorted = $order; sort($sorted);
            $this->require($sorted === $ids && count(array_unique($order)) === count($ids), 'Include every selected side exactly once in the final order.');
            $excluded = array_values(array_unique(array_map('intval', $input['excluded'] ?? [])));
            $this->require(!array_diff($excluded, $ids), 'An excluded side is outside this competition.');
            $this->require(count(array_diff($ids, $excluded)) >= 2, 'At least two eligible sides must remain.');
            $ties = $draw->teamTies()->with('rubbers.teamResults', 'rubbers.draw')->get();
            $complete = $ties->count() === count($ids) * (count($ids) - 1) / 2 && !$ties->contains(fn ($tie) => !app(TeamStandingsService::class)->tieOutcome($tie)['complete']);
            $this->require($complete || !empty($input['accept_incomplete']), 'Explicitly confirm progression with incomplete results.');
            $this->require(!($draw->team_scoring_rules['undecided'] ?? false), 'Confirm scoring first.');
            return ['order' => $order, 'excluded' => $excluded, 'accept_incomplete' => !$complete];
        }, 'standings');
    }

    public function savePlayoff(Draw $draw, TeamTie $tie, array $input): void
    {
        $this->mutate($draw, $input, function (Draw $draw) use ($tie, $input) {
            $this->require(($draw->team_draw_selection['competition']['phase'] ?? null) === 'playoff' && (int) $tie->draw_id === (int) $draw->id, 'Choose a placement encounter in this draw.');
            $tie = $draw->teamTies()->with('rubbers.teamResults', 'rubbers.draw')->findOrFail($tie->id);
            $outcome = app(TeamStandingsService::class)->tieOutcome($tie, false);
            $this->require($outcome['complete'] && $outcome['home'] === $outcome['away'], 'Complete all matches; a deciding result is available only for a level encounter.');
            $winner = (int) $input['winner_team_id'];
            $this->require(in_array($winner, [(int) $tie->home_team_id, (int) $tie->away_team_id], true), 'Choose one of this encounter’s sides.');
            $method = $input['method'];
            $score = null;
            if ($method === 'sets_games') {
                $totals = ['home_sets' => 0, 'away_sets' => 0, 'home_games' => 0, 'away_games' => 0];
                foreach ($tie->rubbers as $rubber) foreach ($totals as $key => $value) $totals[$key] += app(TeamRubberResultService::class)->outcome($rubber)[$key];
                $comparison = [$totals['home_sets'], $totals['home_games']] <=> [$totals['away_sets'], $totals['away_games']];
                $this->require($comparison !== 0 && $winner === (int) ($comparison > 0 ? $tie->home_team_id : $tie->away_team_id), 'The selected side must lead on sets, then games. Use a deciding tie-break or organiser ruling if still level.');
                $score = $totals;
            } elseif ($method === 'mixed_tiebreak') {
                $home = (int) ($input['home_score'] ?? -1); $away = (int) ($input['away_score'] ?? -1);
                $high = max($home, $away); $low = min($home, $away);
                $this->require($low >= 0 && (($high === 10 && $low <= 8) || ($high > 10 && $high - $low === 2)), 'Enter a completed match tie-break to 10, winning by two.');
                $this->require($winner === (int) ($home > $away ? $tie->home_team_id : $tie->away_team_id), 'Winner must match the deciding score.');
                $this->require(trim($input['participants'] ?? '') !== '', 'Record the deciding mixed-doubles players.');
                $score = ['home' => $home, 'away' => $away, 'participants' => trim($input['participants'])];
            }
            return ['tie_id' => $tie->id, 'winner_team_id' => $winner, 'method' => $method, 'score' => $score];
        }, 'playoff_'.$tie->id);
    }

    public function playoffDecision(TeamTie $tie): ?array
    {
        $decision = $tie->draw->team_draw_selection['decisions']['playoff_'.$tie->id] ?? null;
        return $decision && hash_equals($decision['fingerprint'], $this->fingerprint($tie->draw, $tie->id)) ? $decision : null;
    }

    private function mutate(Draw $draw, array $input, callable $build, string $key): void
    {
        DB::transaction(function () use ($draw, $input, $build, $key) {
            app(TeamDrawAdaptationService::class)->lockEvent($draw->event_id);
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            $this->require(!$draw->locked, 'Unlock this draw before recording an organiser decision.');
            $selection = $draw->team_draw_selection ?? [];
            $previous = $selection['decisions'][$key] ?? null;
            $fingerprint = $this->fingerprint($draw, str_starts_with($key, 'playoff_') ? (int) substr($key, 8) : null);
            $next = $build($draw) + ['reason' => trim($input['reason']), 'fingerprint' => $fingerprint];
            // Identical retries do not create a second ruling or audit record.
            if ($previous && array_intersect_key($previous, $next) === $next && $previous['request_id'] === $input['request_id']) return;
            $this->require(hash_equals($this->token($draw), $input['token']), 'Results or organiser decisions changed. Reload, review and save again.');
            $next += ['request_id' => $input['request_id'], 'actor_id' => auth()->id(), 'decided_at' => now()->toIso8601String()];
            $selection['decisions'][$key] = $next;
            $draw->team_draw_selection = $selection; $draw->save();
            DrawAuditLog::record($draw->id, 'organiser_'.$key, null, ['before' => $previous, 'after' => $next]);
        });
    }

    private function require(bool $condition, string $message): void
    {
        if (!$condition) throw ValidationException::withMessages(['decision' => $message]);
    }
}
