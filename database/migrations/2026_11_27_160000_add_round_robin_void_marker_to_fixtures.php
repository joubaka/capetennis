<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixtures', function (Blueprint $table) {
            $table->unsignedBigInteger('round_robin_voided_registration_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fixtures', fn (Blueprint $table) => $table->dropColumn('round_robin_voided_registration_id'));
    }
};
