<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_payouts', function (Blueprint $table) {
            $table->string('purpose', 50)->nullable()->index();
            $table->string('funding_source', 50)->nullable();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->unsignedBigInteger('recipient_user_id')->nullable();
            $table->string('recorded_by_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_payouts', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropIndex(['purpose']);
            $table->dropColumn(['purpose', 'funding_source', 'idempotency_key', 'recipient_user_id', 'recorded_by_name']);
        });
    }
};
