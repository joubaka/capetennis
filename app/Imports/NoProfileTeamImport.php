<?php

namespace App\Imports;

use App\Models\NoProfileTeamPlayer;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class NoProfileTeamImport implements ToCollection, WithHeadingRow
{
  protected ?int $teamId;
  protected int $importedCount = 0;
  protected array $importedTeamIds = [];

  public function __construct(?int $teamId = null)
  {
    $this->teamId = $teamId;
  }

  public function collection(Collection $rows)
  {
    foreach ($rows as $row) {
      // prefer provided teamId, fallback to file column names (teamid or team_id)
      $teamIdFromFile = (int) trim((string) ($row['teamid'] ?? $row['team_id'] ?? '0'));
      $teamId = $this->teamId ?? $teamIdFromFile;

      $rank = (int) trim((string) ($row['rank'] ?? 0));
      $name = trim((string) ($row['name'] ?? ''));
      $surname = trim((string) ($row['surname'] ?? ''));
      $payStatus = (int) trim((string) ($row['paystatus'] ?? $row['pay_status'] ?? 0));
      $email = trim((string) ($row['email'] ?? ''));
      $cell = trim((string) ($row['cell'] ?? $row['cellnr'] ?? ''));

      // require team, rank, name + surname
      if (!$teamId || !$rank || !$name || !$surname) {
        continue;
      }

      try {
        NoProfileTeamPlayer::updateOrCreate(
          [
            'team_id' => $teamId,
            'rank' => $rank,
          ],
          [
            'name' => $name,
            'surname' => $surname,
            'pay_status' => $payStatus,
            'email' => $email ?: null,
            'cellNr' => $cell ?: null,
            'player_profile' => null,
          ]
        );

        $this->importedCount++;
        $this->importedTeamIds[$teamId] = $teamId;
      } catch (\Throwable $e) {
        \Log::warning('NoProfileTeamImport row failed', [
          'team_id' => $teamId,
          'rank' => $rank,
          'error' => $e->getMessage(),
        ]);
        // continue with next row
      }
    }
  }

  public function getImportedCount(): int
  {
    return $this->importedCount;
  }

  public function getImportedTeamIds(): array
  {
    return array_values($this->importedTeamIds);
  }
}

