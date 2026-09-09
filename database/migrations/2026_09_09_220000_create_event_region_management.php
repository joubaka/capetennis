<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These parent tables pre-date Laravel's bigint convention on some
        // installations. Keep the IDs indexed, as the existing team-selection
        // tables do, rather than adding incompatible physical foreign keys.
        // The guards also make this safe to retry after MySQL created the table
        // but failed while adding a foreign key.
        if (! Schema::hasTable('event_region_managers')) {
            Schema::create('event_region_managers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('event_id')->index();
                $table->unsignedBigInteger('event_region_id')->unique();
                $table->unsignedBigInteger('region_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('assigned_by')->nullable()->index();
                $table->timestamps();

                $table->unique(['event_id', 'region_id'], 'event_region_manager_scope_unique');
            });
        }

        if (! Schema::hasTable('team_selection_region_announcements')) {
            Schema::create('team_selection_region_announcements', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('event_id')->index();
                $table->unsignedBigInteger('event_region_id')->index();
                $table->unsignedBigInteger('region_id')->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->string('title');
                $table->text('message');
                $table->timestamp('emailed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['event_id', 'region_id', 'created_at'], 'team_selection_region_announcement_scope');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('team_selection_region_announcements');
        Schema::dropIfExists('event_region_managers');
    }
};
