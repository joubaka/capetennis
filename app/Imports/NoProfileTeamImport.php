<?php

namespace App\Imports;

use App\Models\NoProfileTeamPlayer;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class NoProfileTeamImport implements ToCollection, WithHeadingRow
{
  protected array $importedTeamIds = [];

  public function collection(Collection $rows)
  {
    foreach ($rows as $row) {

      $teamId = (int) trim((string) ($row['teamid'] ?? ''));
      $name = trim((string) ($row['name'] ?? ''));
      $surname = trim((string) ($row['surname'] ?? ''));

      if (!$teamId || !$name || !$surname) {
        continue;
      }

      $this->importedTeamIds[$teamId] = $teamId;

      NoProfileTeamPlayer::updateOrCreate(
        [
          'team_id' => $teamId,
          'rank' => (int) ($row['rank'] ?? 0),
        ],
        [
          'name' => $name,
          'surname' => $surname,
          'pay_status' => (int) ($row['paystatus'] ?? 0),
          'player_profile' => null,
        ]
      );
    }
  }

  public function getImportedTeamIds(): array
  {
    return array_values($this->importedTeamIds);
  }
}

