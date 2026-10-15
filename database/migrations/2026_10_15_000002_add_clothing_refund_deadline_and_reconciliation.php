<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_regions', function (Blueprint $table) {
            $table->timestamp('clothing_deadline_at')->nullable();
        });
        Schema::table('clothing_refunds', function (Blueprint $table) {
            $table->timestamp('clothing_deadline_at')->nullable();
            $table->text('late_refund_note')->nullable();
            $table->string('reconciliation_status', 20)->nullable();
            $table->text('reconciliation_note')->nullable();
            $table->unsignedBigInteger('reconciled_by')->nullable();
            $table->timestamp('reconciled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clothing_refunds', function (Blueprint $table) {
            $table->dropColumn(['clothing_deadline_at', 'late_refund_note', 'reconciliation_status', 'reconciliation_note', 'reconciled_by', 'reconciled_at']);
        });
        Schema::table('event_regions', function (Blueprint $table) {
            $table->dropColumn('clothing_deadline_at');
        });
    }
};
