<?php

namespace App\Imports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExternalTeamWorkbookParser
{
    public function parse(string $path, int $expectedPlayers, ?string $requestedSheet = null): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $workbook = $reader->load($path);
        $parsedSheets = [];

        foreach ($workbook->getWorksheetIterator() as $worksheet) {
            $teams = $this->parseLongTable($worksheet, $expectedPlayers);
            if ($teams === []) {
                $teams = $this->parseVisualBlocks($worksheet, $expectedPlayers);
            }

            $parsedSheets[] = [
                'name' => $worksheet->getTitle(),
                'teams' => $teams,
                'team_count' => count($teams),
                'complete_team_count' => count(array_filter($teams, fn (array $team): bool => $team['selectable'])),
                'player_count' => array_sum(array_column($teams, 'player_count')),
            ];
        }

        $selected = $requestedSheet
            ? collect($parsedSheets)->firstWhere('name', $requestedSheet)
            : collect($parsedSheets)->sortByDesc(fn (array $sheet): int =>
                ($sheet['complete_team_count'] * 10000) + ($sheet['team_count'] * 100) + $sheet['player_count']
            )->first();

        if (! $selected || $selected['team_count'] === 0) {
            return [
                'selected_sheet' => $requestedSheet,
                'sheets' => $this->sheetSummaries($parsedSheets),
                'teams' => [],
                'errors' => [$requestedSheet
                    ? "Sheet {$requestedSheet} does not contain recognizable team rosters."
                    : 'No recognizable team rosters were found in the workbook.'],
            ];
        }

        return [
            'selected_sheet' => $selected['name'],
            'sheets' => $this->sheetSummaries($parsedSheets),
            'teams' => $selected['teams'],
            'errors' => [],
        ];
    }

    private function parseVisualBlocks(Worksheet $sheet, int $expectedPlayers): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $headings = [];

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($column = 2; $column <= $highestColumn; $column++) {
                $value = $this->cellText($sheet, $column, $row);
                $category = $this->parseCategory($value);
                if ($category) {
                    $headings[] = compact('row', 'column', 'value', 'category');
                }
            }
        }

        $teams = [];
        foreach ($headings as $heading) {
            $nextHeading = collect($headings)
                ->where('column', $heading['column'])
                ->where('row', '>', $heading['row'])
                ->sortBy('row')
                ->first();
            $endRow = $nextHeading ? $nextHeading['row'] - 1 : $highestRow;
            $players = [];
            $rowErrors = [];

            for ($row = $heading['row'] + 1; $row <= $endRow; $row++) {
                $rankText = $this->cellText($sheet, $heading['column'] - 1, $row);
                $name = $this->cellText($sheet, $heading['column'], $row);
                $surname = $this->cellText($sheet, $heading['column'] + 1, $row);

                if ($rankText === '' && $name === '' && $surname === '') {
                    continue;
                }

                if ($rankText === '' && ($name !== '' || $surname !== '')) {
                    $rowErrors[] = "Row {$row}: a player name is present without a rank.";
                    continue;
                }

                if (! preg_match('/^\d+$/', $rankText)) {
                    continue;
                }

                $players[] = [
                    'row' => $row,
                    'rank' => (int) $rankText,
                    'name' => $name,
                    'surname' => $surname,
                    'date_of_birth' => null,
                    'email' => null,
                    'cell_nr' => null,
                ];
            }

            $teams[] = $this->validateTeam(
                $heading['category'],
                $heading['value'],
                $players,
                $rowErrors,
                $expectedPlayers
            );
        }

        return $this->rejectDuplicateCategories($teams);
    }

    private function parseLongTable(Worksheet $sheet, int $expectedPlayers): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $header = null;

        for ($row = 1; $row <= min($highestRow, 20); $row++) {
            $columns = [];
            for ($column = 1; $column <= $highestColumn; $column++) {
                $key = $this->headingKey($this->cellText($sheet, $column, $row));
                if ($key !== '') {
                    $columns[$key] = $column;
                }
            }

            if (isset($columns['category'], $columns['rank'], $columns['name'], $columns['surname'])) {
                $header = ['row' => $row, 'columns' => $columns];
                break;
            }
        }

        if (! $header) {
            return [];
        }

        $groups = [];
        for ($row = $header['row'] + 1; $row <= $highestRow; $row++) {
            $categoryText = $this->cellText($sheet, $header['columns']['category'], $row);
            $category = $this->parseCategory($categoryText);
            if (! $category) {
                continue;
            }

            $rankText = $this->cellText($sheet, $header['columns']['rank'], $row);
            $key = $category['key'];
            $groups[$key] ??= ['category' => $category, 'heading' => $categoryText, 'players' => [], 'errors' => []];

            if (! preg_match('/^\d+$/', $rankText)) {
                $groups[$key]['errors'][] = "Row {$row}: rank must be a positive whole number.";
                continue;
            }

            $groups[$key]['players'][] = [
                'row' => $row,
                'rank' => (int) $rankText,
                'name' => $this->cellText($sheet, $header['columns']['name'], $row),
                'surname' => $this->cellText($sheet, $header['columns']['surname'], $row),
                'date_of_birth' => $this->optionalCell($sheet, $header['columns'], ['dateofbirth', 'date_of_birth', 'dob'], $row),
                'email' => $this->optionalCell($sheet, $header['columns'], ['email'], $row),
                'cell_nr' => $this->optionalCell($sheet, $header['columns'], ['cell', 'cellnr', 'cell_nr'], $row),
            ];
        }

        return array_values(array_map(fn (array $group): array => $this->validateTeam(
            $group['category'],
            $group['heading'],
            $group['players'],
            $group['errors'],
            $expectedPlayers
        ), $groups));
    }

    private function validateTeam(array $category, string $heading, array $players, array $errors, int $expectedPlayers): array
    {
        $seenRanks = [];
        $validPlayers = [];
        $blockedRanks = [];

        foreach ($players as $player) {
            if ($player['rank'] < 1) {
                $errors[] = "Row {$player['row']}: rank must be positive.";
                continue;
            }
            if (isset($seenRanks[$player['rank']])) {
                $errors[] = "Rank {$player['rank']} is duplicated on rows {$seenRanks[$player['rank']]} and {$player['row']}.";
                $blockedRanks[] = $player['rank'];
                continue;
            }

            $seenRanks[$player['rank']] = $player['row'];
            if ($player['name'] === '' && $player['surname'] === '') {
                continue;
            }
            if ($player['name'] === '' || $player['surname'] === '') {
                $errors[] = "Row {$player['row']}: both name and surname are required for rank {$player['rank']}.";
                $blockedRanks[] = $player['rank'];
                continue;
            }

            unset($player['row']);
            $validPlayers[] = $player;
        }

        usort($validPlayers, fn (array $left, array $right): int => $left['rank'] <=> $right['rank']);
        $ranks = array_column($validPlayers, 'rank');
        $missing = array_values(array_diff(range(1, $expectedPlayers), $ranks));
        $outside = array_values(array_filter($ranks, fn (int $rank): bool => $rank > $expectedPlayers));

        if (count($validPlayers) !== $expectedPlayers) {
            $errors[] = "Expected {$expectedPlayers} players, but found ".count($validPlayers).'.';
        }
        if ($missing !== []) {
            $errors[] = 'Missing ranks: '.implode(', ', $missing).'.';
        }
        if ($outside !== []) {
            $errors[] = 'Ranks outside the expected team size: '.implode(', ', $outside).'.';
        }

        $errors = array_values(array_unique($errors));
        $blockingErrors = array_values(array_filter(
            $errors,
            fn (string $error): bool => ! str_starts_with($error, "Expected {$expectedPlayers} players, but found ")
                && ! str_starts_with($error, 'Missing ranks:')
        ));
        $placeholderRanks = array_values(array_diff($missing, $blockedRanks));

        return [
            'key' => $category['key'],
            'category' => $category['name'],
            'source_heading' => $heading,
            'players' => $validPlayers,
            'player_count' => count($validPlayers),
            'errors' => $errors,
            'blocking_errors' => $blockingErrors,
            'placeholder_ranks' => $placeholderRanks,
            'can_fill_placeholders' => $missing !== []
                && $placeholderRanks === $missing
                && $blockingErrors === []
                && $outside === [],
            'selectable' => $errors === [],
        ];
    }

    private function rejectDuplicateCategories(array $teams): array
    {
        $counts = array_count_values(array_column($teams, 'key'));

        return array_map(function (array $team) use ($counts): array {
            if (($counts[$team['key']] ?? 0) > 1) {
                $team['errors'][] = "Category {$team['category']} appears more than once on this sheet.";
                $team['errors'] = array_values(array_unique($team['errors']));
                $team['selectable'] = false;
            }

            return $team;
        }, $teams);
    }

    private function parseCategory(string $value): ?array
    {
        $normalized = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value);
        $gender = null;

        if (preg_match('/\b(seuns|boy|boys)\b/', $normalized)) {
            $gender = 'Boys';
        } elseif (preg_match('/\b(dogters|girl|girls)\b/', $normalized)) {
            $gender = 'Girls';
        }

        if (! $gender || ! preg_match('/\b(1[0-9])\b/', preg_replace('/([uo0])(?=1[0-9])/', ' ', $normalized), $ageMatch)) {
            return null;
        }

        $age = (int) $ageMatch[1];

        return [
            'key' => strtolower($gender).'-u'.$age,
            'name' => $gender.' U'.$age,
        ];
    }

    private function headingKey(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9_]+/', '', str_replace(' ', '_', $value)));
    }

    private function optionalCell(Worksheet $sheet, array $columns, array $keys, int $row): ?string
    {
        foreach ($keys as $key) {
            if (isset($columns[$key])) {
                $value = $this->cellText($sheet, $columns[$key], $row);

                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    private function cellText(Worksheet $sheet, int $column, int $row): string
    {
        if ($column < 1) {
            return '';
        }

        $value = $sheet->getCell([$column, $row])->getFormattedValue();

        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    private function sheetSummaries(array $sheets): array
    {
        return array_map(fn (array $sheet): array => [
            'name' => $sheet['name'],
            'team_count' => $sheet['team_count'],
            'complete_team_count' => $sheet['complete_team_count'],
            'player_count' => $sheet['player_count'],
        ], $sheets);
    }
}
