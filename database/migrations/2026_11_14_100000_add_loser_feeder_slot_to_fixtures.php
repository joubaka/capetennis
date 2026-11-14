<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fixtures', 'loser_feeder_slot')) {
            Schema::table('fixtures', function (Blueprint $table) {
                $table->unsignedBigInteger('loser_feeder_slot')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fixtures', 'loser_feeder_slot')) {
            Schema::table('fixtures', fn (Blueprint $table) => $table->dropColumn('loser_feeder_slot'));
        }
    }
};
