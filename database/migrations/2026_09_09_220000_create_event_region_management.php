<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_region_managers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_region_id')->unique()->constrained('event_regions')->cascadeOnDelete();
            $table->foreignId('region_id')->constrained('team_regions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'region_id'], 'event_region_manager_scope_unique');
        });

        Schema::create('team_selection_region_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_region_id')->constrained('event_regions')->cascadeOnDelete();
            $table->foreignId('region_id')->constrained('team_regions')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('message');
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'region_id', 'created_at'], 'team_selection_region_announcement_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_selection_region_announcements');
        Schema::dropIfExists('event_region_managers');
    }
};
