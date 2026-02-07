<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
  public function up(): void
  {
    Schema::table('fixtures', function (Blueprint $table) {
      // After registration1_id / registration2_id
      $table->unsignedBigInteger('registration1_pivot_id')
        ->nullable()
        ->after('registration1_id');

      $table->unsignedBigInteger('registration2_pivot_id')
        ->nullable()
        ->after('registration2_id');

      $table->foreign('registration1_pivot_id', 'fixtures_reg1_pivot_fk')
        ->references('id')
        ->on('draw_group_registrations')
        ->nullOnDelete();

      $table->foreign('registration2_pivot_id', 'fixtures_reg2_pivot_fk')
        ->references('id')
        ->on('draw_group_registrations')
        ->nullOnDelete();
    });
  }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
  public function down(): void
  {
    Schema::table('fixtures', function (Blueprint $table) {
      $table->dropForeign('fixtures_reg1_pivot_fk');
      $table->dropForeign('fixtures_reg2_pivot_fk');
      $table->dropColumn(['registration1_pivot_id', 'registration2_pivot_id']);
    });
  }
};
