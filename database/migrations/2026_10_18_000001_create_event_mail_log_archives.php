<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_mail_log_archives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('log_id');
            $table->unsignedInteger('attempt_number');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('cleared_at');
            $table->unique(['event_id', 'log_id', 'attempt_number'], 'event_mail_archive_attempt_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_mail_log_archives');
    }
};
