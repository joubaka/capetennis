<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixtures', function (Blueprint $table) {
            $table->unsignedBigInteger('registration1_source_group_id')->nullable()->after('registration2_id');
            $table->unsignedInteger('registration1_source_position')->nullable()->after('registration1_source_group_id');
            $table->unsignedBigInteger('registration2_source_group_id')->nullable()->after('registration1_source_position');
            $table->unsignedInteger('registration2_source_position')->nullable()->after('registration2_source_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('fixtures', fn (Blueprint $table) => $table->dropColumn([
            'registration1_source_group_id',
            'registration1_source_position',
            'registration2_source_group_id',
            'registration2_source_position',
        ]));
    }
};
