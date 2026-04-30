<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_payouts', function (Blueprint $table) {
            if (!Schema::hasColumn('event_payouts', 'convenor_id')) {
                $table->unsignedBigInteger('convenor_id')->nullable()->after('event_id');
                $table->foreign('convenor_id')->references('id')->on('event_convenors')->onDelete('set null');
            }

            if (!Schema::hasColumn('event_payouts', 'recipient_name')) {
                $table->string('recipient_name')->nullable()->after('convenor_id');
            }

            if (!Schema::hasColumn('event_payouts', 'payment_method')) {
                $table->string('payment_method')->default('bank_transfer')->after('description');
            }

            if (!Schema::hasColumn('event_payouts', 'reference')) {
                $table->string('reference')->nullable()->after('payment_method');
            }

            if (!Schema::hasColumn('event_payouts', 'paid_by')) {
                $table->unsignedBigInteger('paid_by')->nullable()->after('reference');
                $table->foreign('paid_by')->references('id')->on('users')->onDelete('set null');
            }

            if (!Schema::hasColumn('event_payouts', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('paid_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_payouts', function (Blueprint $table) {
            $table->dropForeign(['convenor_id']);
            $table->dropForeign(['paid_by']);
            $table->dropColumn(['convenor_id', 'recipient_name', 'payment_method', 'reference', 'paid_by', 'paid_at']);
        });
    }
};
