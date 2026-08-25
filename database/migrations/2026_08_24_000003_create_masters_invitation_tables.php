<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('masters_invitation_batches')) {
            Schema::create('masters_invitation_batches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id')->index();
                $table->unsignedBigInteger('series_id')->index();
                $table->string('ranking_run_id', 64)->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedTinyInteger('top_x')->default(0);
                $table->boolean('auto_replacement_enabled')->default(false);
                $table->timestamp('response_deadline')->nullable();
                $table->timestamp('payment_deadline')->nullable();
                $table->timestamp('replacement_payment_deadline')->nullable();
                $table->string('status', 30)->default('draft')->index();
                $table->json('readiness_json')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('masters_invitations')) {
            Schema::create('masters_invitations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('batch_id')->index();
                $table->unsignedBigInteger('event_id')->index();
                $table->unsignedBigInteger('category_event_id')->index();
                $table->unsignedBigInteger('ranking_list_id')->nullable()->index();
                $table->unsignedBigInteger('ranking_category_id')->nullable()->index();
                $table->unsignedBigInteger('player_id')->index();
                $table->unsignedBigInteger('registration_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedInteger('ranking_position');
                $table->unsignedInteger('queue_position');
                $table->unsignedInteger('total_points')->default(0);
                $table->string('status', 40)->default('reserve')->index();
                $table->string('decline_reason', 1000)->nullable();
                $table->string('exception_reason', 1000)->nullable();
                $table->unsignedBigInteger('promoted_from_id')->nullable()->index();
                $table->timestamp('invited_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('declined_at')->nullable();
                $table->timestamp('expired_at')->nullable();
                $table->timestamp('withdrawn_at')->nullable();
                $table->timestamp('replacement_sent_at')->nullable();
                $table->json('snapshot_json')->nullable();
                $table->timestamps();

                $table->unique(['batch_id', 'category_event_id', 'player_id'], 'masters_batch_category_player_unique');
                $table->unique(['batch_id', 'player_id'], 'masters_batch_player_unique');
                $table->index(['batch_id', 'category_event_id', 'queue_position'], 'masters_queue_order_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('masters_invitations');
        Schema::dropIfExists('masters_invitation_batches');
    }
};
