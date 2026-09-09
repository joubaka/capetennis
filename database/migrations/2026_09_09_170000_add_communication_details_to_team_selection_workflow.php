<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_selection_imports', function (Blueprint $table): void {
            $table->string('email_subject', 180)->nullable()->after('payment_deadline');
            $table->text('email_message')->nullable()->after('email_subject');
            $table->text('event_information')->nullable()->after('email_message');
            $table->string('reply_to')->nullable()->after('event_information');
            $table->boolean('include_clothing')->default(false)->after('reply_to');
            $table->char('communication_hash', 64)->nullable()->after('include_clothing');
            $table->unsignedBigInteger('prepared_by')->nullable()->index()->after('communication_hash');
            $table->timestamp('prepared_at')->nullable()->after('prepared_by');
        });

        Schema::table('team_selection_invitations', function (Blueprint $table): void {
            $table->unsignedBigInteger('declined_by_user_id')->nullable()->index()->after('decline_reason');
            $table->string('decline_method', 40)->nullable()->after('declined_by_user_id');
            $table->timestamp('payment_started_at')->nullable()->after('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('team_selection_invitations', function (Blueprint $table): void {
            $table->dropColumn(['declined_by_user_id', 'decline_method', 'payment_started_at']);
        });

        Schema::table('team_selection_imports', function (Blueprint $table): void {
            $table->dropColumn([
                'email_subject', 'email_message', 'event_information', 'reply_to',
                'include_clothing', 'communication_hash', 'prepared_by', 'prepared_at',
            ]);
        });
    }
};
