<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_selection_imports', function (Blueprint $table): void {
            if (! Schema::hasColumn('team_selection_imports', 'auto_replacement_enabled')) {
                $table->boolean('auto_replacement_enabled')->default(true)->after('status');
            }
        });

        Schema::table('team_selection_invitations', function (Blueprint $table): void {
            if (! Schema::hasColumn('team_selection_invitations', 'vacated_roster_rank')) {
                $table->unsignedSmallInteger('vacated_roster_rank')->nullable()->after('roster_rank');
            }
            if (! Schema::hasColumn('team_selection_invitations', 'response_deadline_override')) {
                $table->timestamp('response_deadline_override')->nullable()->after('vacated_roster_rank');
            }
            if (! Schema::hasColumn('team_selection_invitations', 'payment_deadline_override')) {
                $table->timestamp('payment_deadline_override')->nullable()->after('response_deadline_override');
            }
        });
    }

    public function down(): void
    {
        Schema::table('team_selection_invitations', function (Blueprint $table): void {
            if (Schema::hasColumn('team_selection_invitations', 'payment_deadline_override')) {
                $table->dropColumn('payment_deadline_override');
            }
            if (Schema::hasColumn('team_selection_invitations', 'response_deadline_override')) {
                $table->dropColumn('response_deadline_override');
            }
            if (Schema::hasColumn('team_selection_invitations', 'vacated_roster_rank')) {
                $table->dropColumn('vacated_roster_rank');
            }
        });
        Schema::table('team_selection_imports', function (Blueprint $table): void {
            if (Schema::hasColumn('team_selection_imports', 'auto_replacement_enabled')) {
                $table->dropColumn('auto_replacement_enabled');
            }
        });
    }
};
