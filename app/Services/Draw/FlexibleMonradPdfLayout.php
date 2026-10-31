<?php

namespace App\Services\Draw;

use Carbon\Carbon;
use Illuminate\Support\Str;

final class FlexibleMonradPdfLayout
{
    private const CARD_WIDTH = 220;
    private const COLUMN_GAP = 260;
    private const SLOT_HEIGHT = 32;
    private const LINE_GAP = 64;

    public function build(array $state): array
    {
        $matches = (array) ($state['matches'] ?? []);
        $players = collect($state['players'] ?? [])->mapWithKeys(fn ($player) => [(int) $player['id'] => $player]);
        $baseRanks = collect($state['base_positions'] ?? [])->filter(fn ($row) => ! empty($row['player']))
            ->mapWithKeys(fn ($row) => [(int) $row['player'] => (int) $row['position']]);
        $sections = [];
        foreach ($matches as $key => $match) {
            $sections[$match['section']][] = ['key' => $key, 'match' => $match];
        }

        $boards = [];
        $yOffset = 0;
        $maxWidth = 720;
        foreach ($sections as $section => $entries) {
            $sectionTop = $yOffset;
            $rounds = collect($entries)->pluck('match.round')->map(fn ($round) => (int) $round)->unique()->sort()->values();
            $roundColumns = $rounds->flip();
            $layoutEntries = collect($entries)->map(fn ($entry) => [
                'key' => $entry['key'],
                'column' => (int) $roundColumns[(int) $entry['match']['round']],
                'sources' => $entry['match']['sources'],
            ])->all();
            $layout = $this->layout($layoutEntries, $sectionTop + 96);

            $cards = [];
            foreach ($entries as $entry) {
                $key = $entry['key'];
                $match = $entry['match'];
                $position = $layout['positions'][$key];
                $participantLabels = [
                    $this->participantLabel($match, 0, $players, $matches),
                    $this->participantLabel($match, 1, $players, $matches),
                ];
                $cards[] = [
                    'key' => $key,
                    'x' => $position['x'],
                    'top' => $position['top'],
                    'bottom' => $position['bottom'],
                    'middle' => $position['middle'],
                    'width' => self::CARD_WIDTH,
                    'number' => isset($match['challenge_positions']) ? $match['number'].' · '.implode(' v ', $match['challenge_positions']) : $match['number'],
                    'participants' => [
                        ['label' => $participantLabels[0], 'style' => $this->participantStyle($match, 0, $players), 'width' => $this->pillWidth($participantLabels[0])],
                        ['label' => $participantLabels[1], 'style' => $this->participantStyle($match, 1, $players), 'width' => $this->pillWidth($participantLabels[1])],
                    ],
                    'scores' => [
                        collect($match['sets'] ?? [])->pluck(0)->implode(' '),
                        collect($match['sets'] ?? [])->pluck(1)->implode(' '),
                    ],
                    'winner' => $match['winner'] ?? null,
                    'player_ids' => $match['players'] ?? [null, null],
                    'schedule' => $this->scheduleLabel($match['schedule'] ?? null),
                    'note' => ($match['retired'] ?? false) ? 'RET - Retired' : (($match['walkover'] ?? false) ? 'W/O - Walkover' : (isset($match['skip_reason']) ? (str_contains($match['skip_reason'], 'already played') ? 'Skipped - already played' : 'Skipped - player unavailable') : match ($match['automatic'] ?? null) {
                        'walkover' => 'Walkover',
                        'void' => 'Closed - no active players',
                        default => null,
                    })),
                ];
            }

            $connections = [];
            foreach ($entries as $entry) {
                $target = $layout['positions'][$entry['key']];
                foreach ($entry['match']['sources'] as $slot => $source) {
                    $source = (array) $source;
                    if (!isset($source['match'], $layout['positions'][$source['match']])) {
                        continue;
                    }
                    $from = $layout['positions'][$source['match']];
                    $connections[] = [
                        'x1' => $from['x'] + self::CARD_WIDTH,
                        'y1' => $from['middle'],
                        'x2' => $target['x'],
                        'y2' => $slot ? $target['bottom'] : $target['top'],
                    ];
                }
            }

            $followedWinners = collect($entries)->flatMap(fn ($entry) => collect($entry['match']['sources'])
                ->filter(fn ($source) => (($source['type'] ?? null) === 'winner') && !empty($source['match']))
                ->pluck('match'))->all();
            $endpoints = [];
            foreach ($entries as $entry) {
                if (in_array($entry['key'], $followedWinners, true)) {
                    continue;
                }
                $position = $layout['positions'][$entry['key']];
                $lineEnd = $position['x'] + self::CARD_WIDTH + 105;
                preg_match('/^Positions\s+(\d+)/u', $section, $placement);
                $endpoints[] = [
                    'x' => $lineEnd + 10,
                    'y' => $position['middle'] - self::SLOT_HEIGHT,
                    'label' => isset($entry['match']['challenge_positions']) ? 'Position '.$entry['match']['challenge_positions'][0]
                        : ($section === 'Main draw' ? 'Champion' : (isset($placement[1])
                            ? (($state['challenge_playoffs'] ?? false) ? 'Base position ' : 'Position ').($baseRanks[(int) ($entry['match']['winner'] ?? 0)] ?? $placement[1]) : 'Winner')),
                    'name' => ($entry['match']['challenge_status'] ?? null) === 'skipped' ? 'Skipped - positions unchanged' : (!empty($entry['match']['winner'])
                        ? ($players[(int) $entry['match']['winner']]['name'] ?? 'Winner')
                        : ''),
                ];
                $connections[] = [
                    'x1' => $position['x'] + self::CARD_WIDTH,
                    'y1' => $position['middle'],
                    'x2' => $lineEnd,
                    'y2' => $position['middle'],
                ];
                $maxWidth = max($maxWidth, $lineEnd + self::CARD_WIDTH);
            }

            $roundHeadings = $rounds->map(fn ($round, $column) => [
                'x' => $column * self::COLUMN_GAP,
                'y' => $sectionTop + 48,
                'label' => $section === 'Challenge play-offs' ? 'Challenge play-offs'
                    : (collect($entries)->first(fn ($entry) => (int) $entry['match']['round'] === (int) $round)['match']['label'] ?? 'Round '.$round),
            ])->all();

            $boards[] = compact('section', 'sectionTop', 'cards', 'connections', 'endpoints', 'roundHeadings');
            $maxWidth = max($maxWidth, $layout['width']);
            $yOffset = $layout['height'] + 35;
        }

        $positions = collect($state['positions'] ?? [])->map(fn ($position) => [
            'position' => $position['position'],
            'name' => !empty($position['player'])
                ? ($players[(int) $position['player']]['name'] ?? 'Player')
                : (!empty($position['bye']) ? 'Bye - no position awarded' : (!empty($position['vacant']) ? 'Unassigned after withdrawal' : '')),
        ])->all();
        if ($positions) {
            $maxWidth = max($maxWidth, min(8, count($positions)) * 125);
        }

        return [
            'width' => $maxWidth,
            'height' => max(260, $yOffset + ($positions ? 72 : 0)),
            'boards' => $boards,
            'positions' => $positions,
            'positions_label' => ($state['challenge_playoffs'] ?? false) && ! ($state['positions_complete'] ?? false)
                ? 'Provisional positions - challenges pending' : 'Final positions',
            'positions_y' => $yOffset + 12,
        ];
    }

    private function layout(array $entries, int $startY): array
    {
        $matches = collect($entries)->keyBy('key')->all();
        $positions = [];
        $visiting = [];
        $nextLine = $startY;
        $position = function (string $key) use (&$position, &$positions, &$visiting, &$nextLine, $matches): array {
            if (isset($positions[$key])) {
                return $positions[$key];
            }
            if (isset($visiting[$key])) {
                throw new \RuntimeException('Cyclic Flexible Monrad bracket layout.');
            }
            $visiting[$key] = true;
            $match = $matches[$key];
            $lines = [];
            foreach ($match['sources'] as $source) {
                $source = (array) $source;
                $feeder = isset($source['match']) ? ($matches[$source['match']] ?? null) : null;
                if ($feeder && $feeder['column'] < $match['column']) {
                    $lines[] = $position($feeder['key'])['middle'];
                } else {
                    $lines[] = $nextLine;
                    $nextLine += self::LINE_GAP;
                }
            }
            $top = min($lines);
            $bottom = max(max($lines), $top + self::LINE_GAP);
            foreach ($positions as $previous) {
                if ($previous['column'] === $match['column'] && $top <= $previous['bottom'] + self::LINE_GAP / 2 && $bottom >= $previous['top']) {
                    $shift = $previous['bottom'] + self::LINE_GAP - $top;
                    $top += $shift;
                    $bottom += $shift;
                }
            }
            $positions[$key] = [
                'column' => $match['column'],
                'x' => $match['column'] * self::COLUMN_GAP,
                'top' => $top,
                'bottom' => $bottom,
                'middle' => ($top + $bottom) / 2,
            ];
            $nextLine = max($nextLine, $bottom + self::LINE_GAP);
            unset($visiting[$key]);
            return $positions[$key];
        };

        foreach (collect($entries)->sortByDesc('column') as $entry) {
            $position($entry['key']);
        }

        return [
            'positions' => $positions,
            'width' => (collect($entries)->max('column') + 1) * self::COLUMN_GAP,
            'height' => max($startY, collect($positions)->max('bottom')) + 48,
        ];
    }

    private function participantLabel(array $match, int $slot, $players, array $matches): string
    {
        $id = $match['players'][$slot] ?? null;
        if ($id) {
            $player = $players[(int) $id] ?? null;
            return Str::limit(($player['name'] ?? 'Player').(!empty($player['withdrawn']) ? (!empty($player['late_withdrawal']) ? ' (LW)' : ' (W)') : ''), 38);
        }
        $withdrawn = $match['withdrawn_players'][$slot] ?? null;
        if ($withdrawn) {
            return Str::limit(($players[(int) $withdrawn]['name'] ?? 'Withdrawn player').' (W)', 38);
        }
        if (!empty($match['byes'][$slot])) {
            return 'Bye';
        }
        if (!empty($match['vacant'][$slot])) {
            return 'Bye (inactive)';
        }
        $source = (array) ($match['sources'][$slot] ?? []);
        if (isset($source['current_position'])) return 'Current position '.$source['current_position'];
        if (isset($source['base_position'])) return 'Base position '.$source['base_position'];
        if (($source['type'] ?? null) === 'player') {
            return 'Direct entry';
        }
        $type = ($source['type'] ?? null) === 'winner' ? 'Winner' : 'Loser';
        $number = isset($source['match'], $matches[$source['match']]) ? $matches[$source['match']]['number'] : '?';
        return $type.' of Match '.$number;
    }

    private function participantStyle(array $match, int $slot, $players): string
    {
        $id = $match['players'][$slot] ?? null;
        $withdrawn = $match['withdrawn_players'][$slot] ?? null;
        $pending = $match['pending_withdrawal_players'][$slot] ?? null;
        if ($withdrawn || $pending || ($id && !empty($players[(int) $id]['withdrawn']))) {
            return 'withdrawn';
        }
        if ($id) {
            [$homeClass, $awayClass] = \App\Support\ResultPresentation::registrationClasses($match['winner'] ?? null, $match['players'][0] ?? null, $match['players'][1] ?? null);
            $outcome = (int) $id === (int) ($match['players'][0] ?? 0) ? $homeClass : $awayClass;
            return $outcome === 'winner-home' ? 'winner' : ($outcome === 'loser-home' ? 'loser' : 'player');
        }

        return 'source';
    }

    private function pillWidth(string $label): int
    {
        return max(42, min(190, 13 + Str::length($label) * 5));
    }

    private function scheduleLabel(?array $schedule): ?string
    {
        if (empty($schedule['time'])) {
            return null;
        }
        try {
            $label = Carbon::parse($schedule['time'])->format('D d M Y H:i');
        } catch (\Throwable) {
            $label = (string) $schedule['time'];
        }
        if (!empty($schedule['court'])) {
            $label .= ' · Court '.$schedule['court'];
        }

        return $label;
    }
}
