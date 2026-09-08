<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ranking_tie_decisions')) {
            Schema::create('ranking_tie_decisions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('series_id')->index();
                $table->string('run_id', 100)->index();
                $table->unsignedBigInteger('ranking_list_id')->index();
                $table->string('tie_key', 64);
                $table->integer('total_points');
                $table->json('player_ids');
                $table->json('ordered_player_ids');
                $table->string('reason', 40);
                $table->text('note')->nullable();
                $table->unsignedBigInteger('fixture_id')->nullable()->index();
                $table->unsignedBigInteger('confirmed_by')->index();
                $table->timestamp('confirmed_at');
                $table->json('decision_snapshot');
                $table->timestamps();

                $table->unique(
                    ['series_id', 'run_id', 'ranking_list_id', 'tie_key'],
                    'ranking_tie_decision_unique'
                );
            });
        }

        $this->copyExistingHeadToHeadConfirmations();
    }

    private function copyExistingHeadToHeadConfirmations(): void
    {
        if (! Schema::hasTable('ranking_head_to_head_confirmations')) {
            return;
        }

        DB::table('ranking_head_to_head_confirmations')
            ->orderBy('id')
            ->get()
            ->each(function ($legacy): void {
                $players = collect([(int) $legacy->player1_id, (int) $legacy->player2_id])->sort()->values();
                $winner = (int) $legacy->winner_player_id;
                $loser = (int) $players->first(fn (int $playerId) => $playerId !== $winner);
                $points = (int) (DB::table('series_rankings')
                    ->where('series_id', $legacy->series_id)
                    ->where('run_id', $legacy->run_id)
                    ->where('ranking_list_id', $legacy->ranking_list_id)
                    ->whereIn('player_id', $players)
                    ->value('total_points') ?? 0);
                $tieKey = hash('sha256', implode(':', [
                    (int) $legacy->ranking_list_id,
                    $points,
                    $players->implode(','),
                ]));
                $legacySnapshot = json_decode((string) $legacy->decision_snapshot, true) ?: [];
                $decision = array_merge($legacySnapshot, [
                    'tie_key' => $tieKey,
                    'ranking_list_id' => (int) $legacy->ranking_list_id,
                    'total_points' => $points,
                    'player_ids' => $players->all(),
                    'confirmed_order' => [$winner, $loser],
                    'reason' => 'head_to_head',
                    'note' => null,
                    'confirmed_by' => (int) $legacy->confirmed_by,
                    'confirmed_at' => (string) $legacy->confirmed_at,
                ]);

                DB::table('ranking_tie_decisions')->updateOrInsert(
                    [
                        'series_id' => (int) $legacy->series_id,
                        'run_id' => (string) $legacy->run_id,
                        'ranking_list_id' => (int) $legacy->ranking_list_id,
                        'tie_key' => $tieKey,
                    ],
                    [
                        'total_points' => $points,
                        'player_ids' => json_encode($players->all(), JSON_THROW_ON_ERROR),
                        'ordered_player_ids' => json_encode([$winner, $loser], JSON_THROW_ON_ERROR),
                        'reason' => 'head_to_head',
                        'note' => null,
                        'fixture_id' => (int) $legacy->fixture_id,
                        'confirmed_by' => (int) $legacy->confirmed_by,
                        'confirmed_at' => $legacy->confirmed_at,
                        'decision_snapshot' => json_encode($decision, JSON_THROW_ON_ERROR),
                        'created_at' => $legacy->created_at,
                        'updated_at' => $legacy->updated_at,
                    ]
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_tie_decisions');
    }
};
