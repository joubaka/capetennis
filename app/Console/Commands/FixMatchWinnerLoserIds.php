<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TeamFixtureResult;
use App\Models\TeamFixturePlayer;

class FixMatchWinnerLoserIds extends Command
{
  protected $signature = 'fix:match-player-ids {--range=all}';
  protected $description = 'Fix team_fixture_results match_winner_id and match_loser_id by linking to correct player IDs';

  public function handle()
  {
    $this->info('🔍 Starting match_winner_id / match_loser_id fix...');

    $query = TeamFixtureResult::query();

    // Optional range filter (e.g., --range=49000-49300)
    if ($range = $this->option('range')) {
      if ($range !== 'all' && preg_match('/^(\d+)-(\d+)$/', $range, $m)) {
        $query->whereBetween('team_fixture_id', [$m[1], $m[2]]);
        $this->info("📘 Limiting to fixture range: {$m[1]}–{$m[2]}");
      }
    }

    $results = $query->get();
    $updated = 0;

    foreach ($results as $result) {
      $fixturePlayers = TeamFixturePlayer::where('team_fixture_id', $result->team_fixture_id)->first();

      if (!$fixturePlayers) {
        $this->warn("⚠️ No players linked to fixture {$result->team_fixture_id}");
        continue;
      }

      $winner = $result->match_winner_id;
      $loser = $result->match_loser_id;

      $winnerPlayerId = null;
      $loserPlayerId = null;

      // 🧠 1 or 2 = team sides; otherwise assume already player IDs
      if ($winner == 1)
        $winnerPlayerId = $fixturePlayers->team1_id;
      elseif ($winner == 2)
        $winnerPlayerId = $fixturePlayers->team2_id;
      elseif (in_array($winner, [$fixturePlayers->team1_id, $fixturePlayers->team2_id]))
        $winnerPlayerId = $winner;

      if ($loser == 1)
        $loserPlayerId = $fixturePlayers->team1_id;
      elseif ($loser == 2)
        $loserPlayerId = $fixturePlayers->team2_id;
      elseif (in_array($loser, [$fixturePlayers->team1_id, $fixturePlayers->team2_id]))
        $loserPlayerId = $loser;

      if ($winnerPlayerId && $loserPlayerId) {
        $result->update([
          'match_winner_id' => $winnerPlayerId,
          'match_loser_id' => $loserPlayerId,
        ]);
        $this->line("✅ Fixed fixture {$result->team_fixture_id}, result {$result->id} → winner={$winnerPlayerId}, loser={$loserPlayerId}");
        $updated++;
      } else {
        $this->warn("❌ Skipped fixture {$result->team_fixture_id}, result {$result->id} — no valid link");
      }
    }

    $this->info("🎯 Completed — {$updated} results updated.");
  }
}
