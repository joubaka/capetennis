<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clothing_item_types', function (Blueprint $table): void {
            $table->decimal('cost_price', 10, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('clothing_item_types', function (Blueprint $table): void {
            $table->dropColumn('cost_price');
        });
    }
};
