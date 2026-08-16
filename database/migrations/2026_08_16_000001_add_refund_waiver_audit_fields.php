<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['category_event_registrations', 'team_payment_orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->timestamp('refund_waived_at')->nullable()->after('refunded_at');
                $table->foreignId('refund_waived_by')->nullable()->after('refund_waived_at')
                    ->constrained('users')->nullOnDelete();
                $table->string('refund_waiver_reason', 500)->nullable()->after('refund_waived_by');
            });
        }
    }

    public function down(): void
    {
        foreach (['category_event_registrations', 'team_payment_orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('refund_waived_by');
                $table->dropColumn(['refund_waived_at', 'refund_waiver_reason']);
            });
        }
    }
};
