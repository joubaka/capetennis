<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_selection_finalizations', function (Blueprint $table) {
            $table->index('event_id', 'selection_finalizations_event_history_index');
        });
        Schema::table('event_selection_finalizations', function (Blueprint $table) {
            $table->dropUnique(['event_id']);
        });
        Schema::create('event_selection_reopenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finalization_id')->unique()->constrained('event_selection_finalizations');
            $table->foreignId('reopened_by')->constrained('users');
            $table->string('reason', 1000);
            $table->timestamp('reopened_at');
        });
    }

    public function down(): void
    {
        if (DB::table('event_selection_reopenings')->exists()) throw new RuntimeException('Cannot remove selection reopening history.');
        Schema::dropIfExists('event_selection_reopenings');
        Schema::table('event_selection_finalizations', function (Blueprint $table) {
            $table->unique('event_id');
            $table->dropIndex('selection_finalizations_event_history_index');
        });
    }
};
