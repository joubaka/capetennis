<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('category_event_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('category_event_registrations', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('pf_transaction_id');
            }
            if (! Schema::hasColumn('category_event_registrations', 'wallet_transaction_id')) {
                $table->unsignedBigInteger('wallet_transaction_id')->nullable()->after('payment_method');
            }
        });
    }

    public function down(): void {
        Schema::table('category_event_registrations', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('category_event_registrations', 'wallet_transaction_id')) $cols[] = 'wallet_transaction_id';
            if (Schema::hasColumn('category_event_registrations', 'payment_method')) $cols[] = 'payment_method';
            if ($cols) $table->dropColumn($cols);
        });
    }
};

