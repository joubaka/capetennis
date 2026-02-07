<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up()
  {
    Schema::table('draw_groups', function (Blueprint $table) {
      $table->string('category_slug')->nullable()->after('name');
      $table->string('color')->default('primary')->after('category_slug');
      $table->integer('sort_order')->default(0)->after('color');
    });
  }

  public function down()
  {
    Schema::table('draw_groups', function (Blueprint $table) {
      $table->dropColumn(['category_slug', 'color', 'sort_order']);
    });
  }
};

