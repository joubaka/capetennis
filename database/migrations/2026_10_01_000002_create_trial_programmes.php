<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trial_programmes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->unique();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->text('bank_details')->nullable();
            $table->decimal('participation_fee', 10, 2)->default(0);
            $table->timestamp('response_deadline')->nullable();
            $table->timestamp('payment_deadline')->nullable();
            $table->timestamp('concluded_at')->nullable();
            $table->unsignedBigInteger('current_run_id')->nullable();
            $table->timestamps();
        });
        Schema::create('trial_ranking_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->string('signature', 64);
            $table->json('positions');
            $table->timestamps();
            $table->unique(['event_id', 'signature']);
        });
        Schema::create('trial_squad_drafts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('ranking_run_id');
            $table->json('tiers');
            $table->string('status')->default('draft');
            $table->boolean('needs_review')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('finalised_by')->nullable();
            $table->timestamp('finalised_at')->nullable();
            $table->timestamps();
        });
        Schema::create('trial_squad_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('draft_id')->index();
            $table->unsignedBigInteger('category_event_id');
            $table->string('tier', 1);
            $table->unsignedInteger('slot');
            $table->unsignedBigInteger('player_id')->nullable();
            $table->boolean('reserve')->default(false);
            $table->boolean('requires_colour')->default(false);
            $table->string('response')->default('pending');
            $table->unsignedBigInteger('responded_by')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['draft_id', 'category_event_id', 'tier', 'slot'], 'trial_slot_position_unique');
            $table->unique(['draft_id', 'player_id'], 'trial_slot_player_unique');
        });
    }

    public function down(): void
    {
        foreach (['trial_squad_slots', 'trial_squad_drafts', 'trial_ranking_runs', 'trial_programmes'] as $table) {
            if (\Illuminate\Support\Facades\DB::table($table)->exists()) {
                throw new RuntimeException('Retain Trials lifecycle records; archive them before rollback.');
            }
        }
        foreach (['trial_squad_slots', 'trial_squad_drafts', 'trial_ranking_runs', 'trial_programmes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
