<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('players', 'identity_name_hash')) {
            Schema::table('players', function (Blueprint $table) {
                $table->char('identity_name_hash', 64)->nullable()->index();
                $table->char('identity_email_dob_hash', 64)->nullable()->index();
                $table->char('identity_cell_dob_hash', 64)->nullable()->index();
            });

            DB::table('players')->select(['id', 'name', 'surname', 'dateOfBirth', 'email', 'cellNr'])
                ->orderBy('id')->chunkById(500, function ($players) {
                    foreach ($players as $player) {
                        $name = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $player->name)));
                        $surname = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $player->surname)));
                        $dob = substr((string) $player->dateOfBirth, 0, 10);
                        $email = mb_strtolower(trim((string) $player->email));
                        $cell = preg_replace('/[^0-9+]/', '', (string) $player->cellNr) ?? '';

                        DB::table('players')->where('id', $player->id)->update([
                            'identity_name_hash' => $name !== '' && $surname !== '' ? hash('sha256', $name.'|'.$surname) : null,
                            'identity_email_dob_hash' => $dob !== '' && $email !== '' ? hash('sha256', $dob.'|'.$email) : null,
                            'identity_cell_dob_hash' => $dob !== '' && $cell !== '' ? hash('sha256', $dob.'|'.$cell) : null,
                        ]);
                    }
                });
        }

        if (! Schema::hasTable('player_duplicate_decisions')) {
            Schema::create('player_duplicate_decisions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('first_player_id');
                $table->unsignedBigInteger('second_player_id');
                $table->string('decision', 30);
                $table->text('reason');
                $table->unsignedBigInteger('decided_by');
                $table->timestamps();

                $table->unique(['first_player_id', 'second_player_id'], 'player_duplicate_decision_pair_unique');
                $table->index(['decision', 'updated_at']);
                $table->foreign('decided_by')->references('id')->on('users')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('player_merge_audits')) {
            Schema::create('player_merge_audits', function (Blueprint $table) {
                $table->id();
                // Deliberately not foreign keys: removed_player_id must remain resolvable
                // after the source profile is deleted, and canonical IDs may be merged later.
                $table->unsignedBigInteger('kept_player_id');
                $table->unsignedBigInteger('removed_player_id')->unique();
                $table->unsignedBigInteger('approved_by');
                $table->text('reason');
                $table->string('status', 30)->default('completed');
                $table->json('kept_before_snapshot');
                $table->json('removed_snapshot');
                $table->json('field_resolutions')->nullable();
                $table->json('impact_snapshot');
                $table->json('change_manifest');
                $table->timestamp('merged_at');
                $table->timestamps();

                $table->index('kept_player_id');
                $table->index(['approved_by', 'merged_at']);
                $table->foreign('approved_by')->references('id')->on('users')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('player_merge_audits');
        Schema::dropIfExists('player_duplicate_decisions');

        if (Schema::hasColumn('players', 'identity_name_hash')) {
            Schema::table('players', function (Blueprint $table) {
                $table->dropColumn(['identity_name_hash', 'identity_email_dob_hash', 'identity_cell_dob_hash']);
            });
        }
    }
};
