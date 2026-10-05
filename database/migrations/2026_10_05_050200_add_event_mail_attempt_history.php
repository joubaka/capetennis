<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulk_email_logs', function (Blueprint $table) {
            $table->unsignedInteger('attempt_number')->default(1);
            $table->unsignedBigInteger('retry_actor_id')->nullable();
        });
        Schema::create('event_mail_attempt_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('log_id')->index();
            $table->unsignedInteger('attempt_number');
            $table->string('status', 30);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('snapshot');
            $table->timestamp('recorded_at');
        });
        Schema::table('event_mail_issues', function (Blueprint $table) {
            $table->dropUnique(['log_id', 'kind']);
            $table->unsignedInteger('attempt_number')->default(1);
            $table->unique(['log_id', 'attempt_number', 'kind', 'user_id'], 'mail_issue_attempt_actor_unique');
        });
    }
    public function down(): void
    {
        // A downgrade cannot collapse per-attempt alerts without losing evidence.
        $duplicates = DB::table('event_mail_issues')->select('log_id', 'kind')->groupBy('log_id', 'kind')->havingRaw('COUNT(*) > 1')->exists();
        if ($duplicates) {
            throw new RuntimeException('Retain attempt history: duplicate per-attempt issues prevent a safe downgrade.');
        }
        Schema::table('event_mail_issues', function (Blueprint $table) {
            $table->dropUnique('mail_issue_attempt_actor_unique');
            $table->dropColumn('attempt_number');
            $table->unique(['log_id', 'kind']);
        });
        Schema::dropIfExists('event_mail_attempt_history');
        Schema::table('bulk_email_logs', function (Blueprint $table) {
            $table->dropColumn(['attempt_number', 'retry_actor_id']);
        });
    }
};
