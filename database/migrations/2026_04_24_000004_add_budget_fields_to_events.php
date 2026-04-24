<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Budget cap — total spending limit for the event
            $table->decimal('budget_cap', 10, 2)->nullable()->after('cape_tennis_fee');

            // Income targets — for tracking against actuals in the finances view
            $table->integer('target_entries')->nullable()->after('budget_cap');
            $table->decimal('target_income', 10, 2)->nullable()->after('target_entries');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['budget_cap', 'target_entries', 'target_income']);
        });
    }
};
