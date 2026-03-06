<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_expenses', function (Blueprint $table) {
            $table->string('convenor_name', 100)->nullable()->after('expense_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_expenses', function (Blueprint $table) {
            $table->dropColumn('convenor_name');
        });
    }
};
