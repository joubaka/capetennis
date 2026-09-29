<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('registration_orders', 'payfast_handed_off_at')) {
            return;
        }

        Schema::table('registration_orders', function (Blueprint $table): void {
            $table->timestamp('payfast_handed_off_at')->nullable()->after('payfast_amount_due');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('registration_orders', 'payfast_handed_off_at')) {
            return;
        }

        Schema::table('registration_orders', function (Blueprint $table): void {
            $table->dropColumn('payfast_handed_off_at');
        });
    }
};
