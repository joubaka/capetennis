<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_payouts', function (Blueprint $table) {
            $table->json('settlement_details')->nullable();
        });
        Schema::table('regional_clothing_settlements', function (Blueprint $table) {
            $table->foreignId('event_payout_id')->nullable()->constrained('event_payouts')->restrictOnDelete();
            $table->unique(['event_payout_id', 'event_region_id'], 'clothing_director_settlement_leg_unique');
        });
    }

    public function down(): void
    {
        Schema::table('regional_clothing_settlements', function (Blueprint $table) {
            $table->dropUnique('clothing_director_settlement_leg_unique');
            $table->dropConstrainedForeignId('event_payout_id');
        });
        Schema::table('event_payouts', function (Blueprint $table) {
            $table->dropColumn('settlement_details');
        });
    }
};
