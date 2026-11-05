<?php

namespace App\Services\Performance;

use App\Models\Player;
use Carbon\CarbonImmutable;

/** A bounded display projection, never a new rating calculation. */
class PlayerPlusRecordService
{
    public function __construct(private readonly PlayerRatingHistoryService $history) {}

    public function forPlayer(Player $player, array $filters = [], bool $includeTeamSheet = false): array
    {
        $source = $this->history->recordSource();
        $matches = []; $conflicts = [];
        foreach ($source['evidence'] ?? [] as $row) {
            if ($row['kind'] !== 'match' || !($row['months'] ?? 0) || $row['date'] > $source['as_of']
                || (!$includeTeamSheet && str_starts_with($row['source_id'], 'teamsheet:'))) continue;
            $side = array_search((int) $player->id, $row['players'], true);
            if ($side === false) continue;
            $other = $side === 0 ? 1 : 0;
            $match = ['id' => $row['source_id'], 'event' => $row['event'], 'event_id' => $row['event_id'], 'date' => $row['date'],
                'date_basis' => $row['date_basis'], 'opponent_id' => $row['players'][$other], 'opponent' => $row['names'][$other],
                'won' => $row['winner_side'] === $side + 1, 'score' => $side === 0 ? $row['score'] : preg_replace_callback('/(\d+)-(\d+)/', fn ($pair) => $pair[2].'-'.$pair[1], $row['score'])];
            if (isset($matches[$row['source_id']]) && $matches[$row['source_id']] !== $match) $conflicts[$row['source_id']] = true;
            $matches[$row['source_id']] = $match;
        }
        $matches = array_values(array_diff_key($matches, $conflicts));
        usort($matches, fn ($a, $b) => strcmp($a['date'], $b['date']) ?: strcmp($a['id'], $b['id']));
        $years = array_values(array_unique(array_map(fn ($row) => substr($row['date'], 0, 4), $matches))); rsort($years);
        $first = $matches[0] ?? null;
        $firstWin = collect($matches)->firstWhere('won', true);
        $coverageStart = $first['date'] ?? null;
        $year = (string) ($filters['year'] ?? '');
        $selected = array_values(array_filter($matches, fn ($row) => $year === '' || substr($row['date'], 0, 4) === $year));
        $monthly = [];
        if ($selected) {
            $start = $year === ''
                ? CarbonImmutable::parse($source['as_of'])->startOfMonth()->subMonths(11)
                : CarbonImmutable::create((int) $year, 1, 1);
            for ($i = 0; $i < 12; $i++) {
                $date = $start->addMonths($i);
                $monthly[$date->format('Y-m')] = ['label' => $date->format('M Y'), 'wins' => 0, 'losses' => 0];
            }
            foreach ($selected as $match) {
                $key = substr($match['date'], 0, 7);
                if (isset($monthly[$key])) $monthly[$key][$match['won'] ? 'wins' : 'losses']++;
            }
        }
        $recordMonth = (string) ($filters['record_month'] ?? '');
        $register = $recordMonth === '' ? $selected : array_values(array_filter($selected, fn ($row) => substr($row['date'], 0, 7) === $recordMonth));
        $seasons = [];
        foreach ($matches as $match) {
            $key = substr($match['date'], 0, 4);
            $seasons[$key] ??= ['year' => $key, 'played' => 0, 'wins' => 0];
            $seasons[$key]['played']++; $seasons[$key]['wins'] += (int) $match['won'];
        }
        krsort($seasons);
        $opponents = [];
        foreach ($selected as $match) {
            $id = $match['opponent_id'];
            $opponents[$id] ??= ['id' => $id, 'name' => $match['opponent'], 'played' => 0, 'wins' => 0, 'last' => $match['date']];
            $opponents[$id]['played']++; $opponents[$id]['wins'] += (int) $match['won']; $opponents[$id]['last'] = $match['date'];
        }
        uasort($opponents, fn ($a, $b) => $b['played'] <=> $a['played'] ?: strcmp($b['last'], $a['last']) ?: strcmp($a['name'], $b['name']));
        $opponentId = (int) ($filters['opponent'] ?? 0);
        $opponent = $opponents[$opponentId] ?? null;
        $meetings = $opponent ? array_values(array_filter($selected, fn ($row) => $row['opponent_id'] === $opponentId)) : [];
        $firstSeason = (string) (array_key_exists('compare_first', $filters) ? $filters['compare_first'] : ($years[1] ?? $years[0] ?? ''));
        $secondSeason = (string) (array_key_exists('compare_second', $filters) ? $filters['compare_second'] : (isset($years[1]) ? $years[0] : ''));
        $comparison = ['first_year' => $firstSeason, 'second_year' => $secondSeason,
            'first' => $this->summary(array_values(array_filter($matches, fn ($row) => substr($row['date'], 0, 4) === $firstSeason))),
            'second' => $this->summary(array_values(array_filter($matches, fn ($row) => substr($row['date'], 0, 4) === $secondSeason)))];
        $comparison['reason'] = $firstSeason === '' || $secondSeason === '' ? 'Choose two seasons with available results.'
            : ($firstSeason === $secondSeason ? 'Choose different seasons to compare their records.'
                : (!$comparison['first']['played'] || !$comparison['second']['played'] ? 'A selected season has no available match results.' : null));
        $comparison['delta'] = null;
        if ($comparison['reason'] === null) foreach (['played', 'wins', 'losses', 'rate', 'events', 'opponents'] as $key) $comparison['delta'][$key] = round($comparison['second'][$key] - $comparison['first'][$key], 1);
        if ($comparison['reason'] === null) $comparison['delta']['rate'] = round(100 * ($comparison['second']['wins'] / $comparison['second']['played'] - $comparison['first']['wins'] / $comparison['first']['played']), 1);
        $opponentSeasons = [];
        foreach ($meetings as $match) $opponentSeasons[substr($match['date'], 0, 4)][] = $match;
        ksort($opponentSeasons);
        $opponentTrend = [];
        foreach ($opponentSeasons as $season => $seasonMatches) $opponentTrend[] = ['year' => (string) $season] + $this->summary($seasonMatches);
        return ['reason' => $source['reason'], 'as_of' => $source['as_of'] ?? null, 'built_at' => $source['built_at'] ?? null,
            'stale' => $source['stale'] ?? false, 'coverage_start' => $coverageStart, 'years' => $years, 'year' => $year,
            'first' => $first, 'first_win' => $firstWin, 'seasons' => array_values($seasons),
            'comparison' => $comparison, 'opponent_trend' => $opponentTrend,
            'opponent_recent' => array_slice(array_reverse($meetings), 0, 5),
            'played' => count($selected), 'wins' => count(array_filter($selected, fn ($row) => $row['won'])),
            'form' => array_slice(array_reverse($selected), 0, 5),
            'events' => count(array_unique(array_column($selected, 'event_id'))), 'opponent_count' => count($opponents),
            'monthly' => $monthly, 'record_month' => $recordMonth,
            'results' => $this->page(array_reverse($register), (int) ($filters['page'] ?? 1)),
            'opponents' => $this->page(array_values($opponents), (int) ($filters['opponent_page'] ?? 1)),
            'opponent' => $opponent, 'meetings' => $this->page(array_reverse($meetings), (int) ($filters['meeting_page'] ?? 1))];
    }

    private function summary(array $matches): array
    {
        $played = count($matches); $wins = count(array_filter($matches, fn ($row) => $row['won']));
        return ['played' => $played, 'wins' => $wins, 'losses' => $played - $wins,
            'rate' => $played ? round(100 * $wins / $played, 1) : null,
            'from' => $played ? min(array_column($matches, 'date')) : null, 'through' => $played ? max(array_column($matches, 'date')) : null,
            'events' => count(array_unique(array_column($matches, 'event_id'))),
            'opponents' => count(array_unique(array_column($matches, 'opponent_id')))];
    }

    private function page(array $rows, int $page): array
    {
        $pages = max(1, (int) ceil(count($rows) / 20)); $page = max(1, min($page, $pages));
        return ['rows' => array_slice($rows, ($page - 1) * 20, 20), 'total' => count($rows), 'page' => $page, 'pages' => $pages];
    }
}
