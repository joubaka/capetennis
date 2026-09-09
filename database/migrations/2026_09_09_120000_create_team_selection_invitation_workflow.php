<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_region_ranking_sources', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('event_region_id')->unique();
            $table->unsignedBigInteger('region_id')->index();
            $table->unsignedBigInteger('series_id')->index();
            $table->unsignedTinyInteger('reserve_count')->default(2);
            $table->unsignedBigInteger('linked_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['event_id', 'region_id'], 'event_region_ranking_source_unique');
        });

        Schema::create('team_selection_imports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_id')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('region_id')->index();
            $table->unsignedBigInteger('series_id')->index();
            $table->string('ranking_run_id', 64)->index();
            $table->unsignedBigInteger('imported_by')->nullable()->index();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('response_deadline')->nullable();
            $table->timestamp('payment_deadline')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'ranking_run_id'], 'team_selection_source_run_unique');
        });

        Schema::create('team_selection_invitations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('import_id')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('region_id')->index();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('player_id')->index();
            $table->unsignedBigInteger('ranking_list_id')->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedInteger('ranking_position');
            $table->unsignedInteger('queue_position');
            $table->decimal('total_points', 12, 2)->default(0);
            $table->unsignedSmallInteger('roster_rank')->nullable();
            $table->string('status', 40)->default('reserve')->index();
            $table->string('decline_reason', 1000)->nullable();
            $table->unsignedBigInteger('promoted_from_id')->nullable()->index();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->json('snapshot_json')->nullable();
            $table->timestamps();

            $table->unique(['import_id', 'player_id'], 'team_selection_import_player_unique');
            $table->index(['import_id', 'team_id', 'queue_position'], 'team_selection_queue_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_selection_invitations');
        Schema::dropIfExists('team_selection_imports');
        Schema::dropIfExists('event_region_ranking_sources');
    }
};
