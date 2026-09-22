<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_payment_orders', function (Blueprint $table): void {
            $table->string('collection_status')->nullable()->after('pay_status');
            $table->timestamp('paid_privately_at')->nullable()->after('collection_status');
            $table->unsignedBigInteger('paid_privately_by')->nullable()->after('paid_privately_at');
        });
    }

    public function down(): void
    {
        Schema::table('team_payment_orders', function (Blueprint $table): void {
            $table->dropColumn(['collection_status', 'paid_privately_at', 'paid_privately_by']);
        });
    }
};
