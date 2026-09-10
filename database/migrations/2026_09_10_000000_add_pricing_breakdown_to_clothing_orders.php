<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clothing_orders', function (Blueprint $table): void {
            // Clean installations do not necessarily have the legacy address
            // columns, so these fields must not depend on town_text existing.
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('payfast_fee', 10, 2)->default(0);
        });

        DB::table('clothing_orders')->update([
            'subtotal' => DB::raw('COALESCE(total, 0)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('clothing_orders', function (Blueprint $table): void {
            $table->dropColumn(['subtotal', 'payfast_fee']);
        });
    }
};
