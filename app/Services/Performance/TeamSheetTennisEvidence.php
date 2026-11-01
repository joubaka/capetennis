<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Namespaced staged evidence, separate from Cape Tennis fixtures and events. */
class TeamSheetTennisEvidence
{
    public function fingerprint(): string
    {
        $import = app(TeamSheetTennisImportService::class);
        if (!$import->ready()) return 'unavailable';
        $hash = hash_init('sha256');
        hash_update($hash, json_encode([$import->origin(), $import->fresh()], JSON_THROW_ON_ERROR));
        foreach (['teamsheet_tennis_feeds', 'teamsheet_tennis_competitions', 'teamsheet_tennis_player_links', 'teamsheet_tennis_matches'] as $table) {
            if (DB::table($table)->count() > TeamSheetTennisImportService::CAP) return 'over-limit:'.$table;
            foreach (DB::table($table)->orderBy('id')->lazyById(200) as $row) {
                $values = (array) $row;
                unset($values['created_at'], $values['updated_at']);
                if ($table === 'teamsheet_tennis_feeds') unset($values['version'], $values['last_success_at']);
                hash_update($hash, $table.json_encode($values, JSON_THROW_ON_ERROR));
            }
        }
        return hash_final($hash);
    }

    public function published(mixed $context): bool
    {
        return is_string($context) && preg_match('/^[a-f0-9]{64}$/', $context) && app(TeamSheetTennisImportService::class)->fresh() && hash_equals($context, $this->fingerprint());
    }

    /** Source status for the private management page; no source data creates a player profile. */
    public function status(object $row, array $links): array
    {
        $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
        app(TeamSheetTennisImportService::class)->validateRow($payload);
        $a = $links[$payload['side1']['teamsheet_player_id']] ?? null; $b = $links[$payload['side2']['teamsheet_player_id']] ?? null;
        $date = in_array($payload['date_precision'], ['actual_date', 'actual_time'], true) ? CarbonImmutable::parse($payload['played_at'])->toDateString() : null;
        if ($row->reviewed_version === $row->source_version && $row->reviewed_played_on) $date = $row->reviewed_played_on;
        $reason = $row->retracted ? 'No longer eligible or present in the verified source snapshot'
            : (!$a || !$b ? 'Both player identities need reviewed Cape Tennis links'
                : ($a === $b ? 'Both identities resolve to the same player' : (!$date ? 'Actual playing date needs evidence and review' : null)));
        return compact('payload', 'a', 'b', 'date', 'reason');
    }

    public function links(): array
    {
        return DB::table('teamsheet_tennis_player_links as l')->join('players as p', 'p.id', '=', 'l.player_id')
            ->limit(TeamSheetTennisImportService::CAP + 1)->pluck('l.player_id', 'l.teamsheet_player_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Exact player pair, playing date and score; orientation-independent, never a fuzzy identity match. */
    public function duplicateKey(int $a, int $b, string $date, string $score): ?string
    {
        if (!preg_match('/^\d+-\d+(?: \d+-\d+)*$/', trim($score))) return null;
        if ($a > $b) $score = preg_replace_callback('/(\d+)-(\d+)/', fn ($pair) => $pair[2].'-'.$pair[1], $score);
        return hash('sha256', json_encode([min($a, $b), max($a, $b), $date, trim($score)], JSON_THROW_ON_ERROR));
    }

    public function matches(CarbonImmutable $asOf, array $nativeKeys = [], ?CarbonImmutable $completedBefore = null, array &$held = []): array
    {
        $import = app(TeamSheetTennisImportService::class);
        if (!$import->fresh()) return [];
        if (DB::table('teamsheet_tennis_matches')->count() > TeamSheetTennisImportService::CAP) throw new \OverflowException('Too many TeamSheet matches.');
        $links = $this->links(); $matches = []; $externalKeys = [];
        foreach (DB::table('teamsheet_tennis_matches')->where('retracted', false)->orderBy('id')->lazyById(200) as $row) {
            $state = $this->status($row, $links);
            if ($state['reason'] || $state['date'] > $asOf->toDateString() || ($completedBefore && $state['date'] >= $completedBefore->toDateString())) continue;
            $payload = $state['payload']; $a = $state['a']; $b = $state['b'];
            $sets = array_map(fn ($set) => [$set['side1_games'], $set['side2_games']], $payload['sets']);
            $score = implode(' ', array_map(fn ($set) => $set[0].'-'.$set[1], $sets));
            $key = $this->duplicateKey($a, $b, $state['date'], $score);
            $kind = $payload['competition']['type'];
            // Cape results have priority. Cross-source native collisions wait for manual source correction.
            if (isset($nativeKeys[$key]) || (isset($externalKeys[$key]) && $externalKeys[$key] !== $kind)) {
                $held['teamsheet:'.$row->source_match_id] = 'Possible duplicate across result sources; source correction required';
                continue;
            }
            $externalKeys[$key] = $kind;
            $winnerSide = $payload['winner_player_id'] === $payload['side1']['teamsheet_player_id'] ? 1 : 2;
            $sourceId = 'teamsheet:'.$row->source_match_id;
            $eventId = -(int) $row->competition_id;
            $event = 'TeamSheet '.ucfirst($kind).' #'.$payload['competition']['id'];
            $basis = $row->reviewed_version === $row->source_version && $row->reviewed_played_on ? 'reviewed actual date' : 'actual match date';
            $matches[] = ['winner' => $winnerSide === 1 ? $a : $b, 'loser' => $winnerSide === 1 ? $b : $a, 'source_id' => $sourceId,
                'event_id' => $eventId, 'event_name' => $event, 'category' => 'TeamSheet singles', 'cohort' => 'TeamSheet tennis',
                'confidence_date' => $state['date'], 'confidence_date_basis' => $basis, 'outcome_target' => app(PlayedMatchMarginPolicy::class)->target($sets, $winnerSide),
                'player1_id' => $a, 'player2_id' => $b, 'winner_side' => $winnerSide, 'source' => 'TeamSheet', 'event' => $event, 'date' => $state['date'], 'score' => $score];
        }
        return $matches;
    }
}
