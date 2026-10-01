<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trial_replacement_proposals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('draft_id')->index();
            $table->unsignedBigInteger('target_slot_id');
            $table->unsignedBigInteger('source_slot_id')->nullable();
            $table->unsignedBigInteger('target_player_id')->nullable();
            $table->unsignedBigInteger('source_player_id')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });
        Schema::create('trial_ranking_dispositions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('entry_id')->unique();
            $table->string('disposition');
            $table->unsignedBigInteger('changed_by');
            $table->text('reason');
            $table->timestamps();
        });
        Schema::table('trial_programmes', fn (Blueprint $table) => $table->json('manual_position_categories')->nullable());
    }

    public function down(): void
    {
        foreach (['trial_replacement_proposals', 'trial_ranking_dispositions'] as $table) {
            if (\Illuminate\Support\Facades\DB::table($table)->exists()) {
                throw new RuntimeException('Retain Trials decision history before rollback.');
            }
        }
        foreach (['trial_replacement_proposals', 'trial_ranking_dispositions'] as $table) { Schema::dropIfExists($table); }
        Schema::table('trial_programmes', fn (Blueprint $table) => $table->dropColumn('manual_position_categories'));
    }
};
