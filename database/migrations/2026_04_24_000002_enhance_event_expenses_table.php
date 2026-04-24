<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('event_expenses', 'paid_by_convenor_id')) {
            return;
        }

        Schema::table('event_expenses', function (Blueprint $table) {
            // Link expense to the convenor who paid it (replaces free-text convenor_name)
            $table->unsignedBigInteger('paid_by_convenor_id')->nullable()->after('convenor_name');
            $table->foreign('paid_by_convenor_id')
                  ->references('id')->on('event_convenors')
                  ->nullOnDelete();

            // Itemised quantity/price (amount = quantity × unit_price when both set)
            $table->decimal('quantity', 10, 2)->nullable()->after('amount');
            $table->decimal('unit_price', 10, 2)->nullable()->after('quantity');

            // For convenor-payment lines (recipient of the payment)
            $table->string('recipient_name', 150)->nullable()->after('unit_price');

            // Budget vs. actual tracking
            $table->decimal('budget_amount', 10, 2)->nullable()->after('recipient_name');

            // Receipt / slip attachment (relative storage path)
            $table->string('receipt_path', 500)->nullable()->after('budget_amount');

            // Reimbursement tracking
            $table->timestamp('reimbursed_at')->nullable()->after('receipt_path');
            $table->unsignedBigInteger('reimbursed_by')->nullable()->after('reimbursed_at');
            $table->foreign('reimbursed_by')->references('id')->on('users')->nullOnDelete();

            // Approval workflow
            $table->timestamp('approved_at')->nullable()->after('reimbursed_by');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_expenses', function (Blueprint $table) {
            $table->dropForeign(['paid_by_convenor_id']);
            $table->dropForeign(['reimbursed_by']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'paid_by_convenor_id',
                'quantity',
                'unit_price',
                'recipient_name',
                'budget_amount',
                'receipt_path',
                'reimbursed_at',
                'reimbursed_by',
                'approved_at',
                'approved_by',
            ]);
        });
    }
};
