<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('category_event_registrations', 'wallet_transaction_id')) {
            Schema::table('category_event_registrations', function (Blueprint $table) {
                $table->string('payment_method', 20)->nullable()->after('pf_transaction_id');
                $table->unsignedBigInteger('wallet_transaction_id')->nullable()->after('payment_method');
            });
        }
    }

    public function down(): void
    {
        Schema::table('category_event_registrations', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'wallet_transaction_id']);
        });
    }
};
