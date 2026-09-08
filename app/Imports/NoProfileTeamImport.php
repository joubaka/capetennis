<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class NoProfileTeamImport implements ToCollection, WithHeadingRow
{
    private array $rows = [];
    private array $errors = [];

    public function collection(Collection $rows): void
    {
        $seenRanks = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $rank = (int) trim((string) ($row['rank'] ?? 0));
            $name = trim((string) ($row['name'] ?? $row['first_name'] ?? ''));
            $surname = trim((string) ($row['surname'] ?? $row['last_name'] ?? ''));
            $dateOfBirth = $this->normalizeDate($row['dateofbirth'] ?? $row['date_of_birth'] ?? $row['dob'] ?? null);
            $email = trim((string) ($row['email'] ?? ''));
            $cell = trim((string) ($row['cell'] ?? $row['cellnr'] ?? $row['cell_nr'] ?? ''));

            if ($rank === 0 && $name === '' && $surname === '') {
                continue;
            }

            if ($rank < 1) $this->errors[] = "Row {$rowNumber}: Rank must be a positive number.";
            if ($name === '') $this->errors[] = "Row {$rowNumber}: Name is required.";
            if ($surname === '') $this->errors[] = "Row {$rowNumber}: Surname is required.";
            if ($rank > 0 && isset($seenRanks[$rank])) {
                $this->errors[] = "Row {$rowNumber}: Rank {$rank} is duplicated (first used on row {$seenRanks[$rank]}).";
            }
            if ($dateOfBirth !== null && ! $this->validDate($dateOfBirth)) {
                $this->errors[] = "Row {$rowNumber}: Date of birth must use YYYY-MM-DD.";
            }
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = "Row {$rowNumber}: Email address is invalid.";
            }

            if ($rank < 1 || $name === '' || $surname === '') continue;

            $seenRanks[$rank] = $rowNumber;
            $this->rows[] = [
                'row' => $rowNumber,
                'rank' => $rank,
                'name' => preg_replace('/\s+/u', ' ', $name) ?: $name,
                'surname' => preg_replace('/\s+/u', ' ', $surname) ?: $surname,
                'date_of_birth' => $dateOfBirth,
                'email' => $email !== '' ? mb_strtolower($email) : null,
                'cell_nr' => $cell !== '' ? $cell : null,
            ];
        }

        usort($this->rows, fn (array $a, array $b): int => $a['rank'] <=> $b['rank']);
    }

    public function rows(): array
    {
        return $this->rows;
    }

    public function errors(): array
    {
        return array_values(array_unique($this->errors));
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') return null;

        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        return trim((string) $value);
    }
}

