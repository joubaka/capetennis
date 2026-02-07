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
  public function up()
  {
    Schema::table('fixtures', function (Blueprint $table) {
      $table->string('stage')->nullable()->after('round');
    });
  }

  public function down()
  {
    Schema::table('fixtures', function (Blueprint $table) {
      $table->dropColumn('stage');
    });
  }

};
