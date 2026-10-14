<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_selection_roster_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events');
            $table->foreignId('finalization_id')->constrained('event_selection_finalizations');
            $table->foreignId('region_id')->constrained('team_regions');
            $table->foreignId('imported_by')->constrained('users');
            $table->json('groups');
            $table->timestamp('imported_at');
            $table->unique(['event_id', 'finalization_id', 'region_id'], 'selection_roster_import_unique');
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('event_selection_roster_imports')->exists()) {
            throw new RuntimeException('Cannot remove imported selection history.');
        }
        Schema::dropIfExists('event_selection_roster_imports');
    }
};
