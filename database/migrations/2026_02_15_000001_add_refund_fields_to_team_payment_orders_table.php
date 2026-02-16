<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_payment_orders', function (Blueprint $table) {
            $table->string('refund_method')->nullable()->after('payfast_raw_data');
            $table->string('refund_status')->nullable()->after('refund_method');

            $table->decimal('refund_gross', 10, 2)->default(0)->after('refund_status');
            $table->decimal('refund_fee', 10, 2)->default(0)->after('refund_gross');
            $table->decimal('refund_net', 10, 2)->default(0)->after('refund_fee');
            $table->timestamp('refunded_at')->nullable()->after('refund_net');

            $table->string('refund_account_name')->nullable()->after('refunded_at');
            $table->string('refund_bank_name')->nullable()->after('refund_account_name');
            $table->string('refund_account_number')->nullable()->after('refund_bank_name');
            $table->string('refund_branch_code')->nullable()->after('refund_account_number');
            $table->string('refund_account_type')->nullable()->after('refund_branch_code');
        });
    }

    public function down(): void
    {
        Schema::table('team_payment_orders', function (Blueprint $table) {
            $table->dropColumn([
                'refund_method',
                'refund_status',
                'refund_gross',
                'refund_fee',
                'refund_net',
                'refunded_at',
                'refund_account_name',
                'refund_bank_name',
                'refund_account_number',
                'refund_branch_code',
                'refund_account_type',
            ]);
        });
    }
};
