<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_selection_finalizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->foreignId('finalized_by')->constrained('users');
            $table->json('draft_versions');
            $table->json('groups');
            $table->string('fingerprint', 64);
            $table->unsignedTinyInteger('team_size');
            $table->unsignedTinyInteger('reserve_count');
            $table->timestamp('finalized_at');
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('event_selection_finalizations')->exists()) throw new RuntimeException('Cannot remove finalized selection rankings.');
        Schema::dropIfExists('event_selection_finalizations');
    }
};
