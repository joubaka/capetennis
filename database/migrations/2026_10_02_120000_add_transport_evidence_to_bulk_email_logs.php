<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulk_email_logs', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable();
            $table->string('transport_message_id')->nullable();
            $table->string('transport_name', 100)->nullable();
            $table->string('evidence_status', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bulk_email_logs', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'transport_message_id', 'transport_name', 'evidence_status']);
        });
    }
};
