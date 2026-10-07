<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_result_selection_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('group_key', 40);
            $table->unsignedInteger('version');
            $table->json('region_ids');
            $table->json('formats');
            $table->json('selected_keys');
            $table->json('reasons');
            $table->json('snapshot');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'group_key']);
        });
        Schema::create('team_result_selection_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('draft_id')->constrained('team_result_selection_drafts')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('evidence');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->unique(['draft_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_result_selection_revisions');
        Schema::dropIfExists('team_result_selection_drafts');
    }
};
