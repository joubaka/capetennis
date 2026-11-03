<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_payouts', function (Blueprint $table) {
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('event_payouts')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_payouts', function (Blueprint $table) {
            $table->dropForeign(['reversal_of_id']);
            $table->dropUnique(['reversal_of_id']);
            $table->dropColumn(['reversal_of_id', 'reversal_reason']);
        });
    }
};
