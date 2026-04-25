<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_convenors', function (Blueprint $table) {
            // Percentage of net profit allocated to this convenor (0–100). Null = no profit share.
            $table->decimal('profit_share_pct', 5, 2)->nullable()->default(null)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('event_convenors', function (Blueprint $table) {
            $table->dropColumn('profit_share_pct');
        });
    }
};
