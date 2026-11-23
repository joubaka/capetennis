<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('plus_digest_preferences', function (Blueprint $table) { $table->timestamp('event_resume_epoch')->nullable(); });
        Schema::create('plus_event_update_baselines', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->unsignedBigInteger('event_id');
            $table->string('epoch'); $table->unsignedInteger('generation'); $table->json('snapshot'); $table->unique(['user_id', 'event_id']);
        });
        Schema::create('plus_event_update_states', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('event_id')->unique();
            $table->unsignedInteger('generation')->default(0); $table->json('snapshot');
            $table->json('changes'); $table->timestamp('changed_at')->nullable();
            $table->timestamp('due_at')->nullable()->index(); $table->timestamp('prepared_at')->nullable(); $table->timestamps();
        });
        Schema::create('plus_event_update_deliveries', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('state_id')->index(); $table->unsignedInteger('generation');
            $table->string('recipient'); $table->json('player_ids'); $table->json('snapshot'); $table->json('changes');
            $table->string('deduplication_key', 64)->unique(); $table->string('status')->default('pending')->index();
            $table->timestamp('claimed_at')->nullable(); $table->timestamp('sent_at')->nullable(); $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('plus_event_update_deliveries'); Schema::dropIfExists('plus_event_update_states');
        Schema::dropIfExists('plus_event_update_baselines');
        Schema::table('plus_digest_preferences', fn (Blueprint $table) => $table->dropColumn('event_resume_epoch'));
    }
};
