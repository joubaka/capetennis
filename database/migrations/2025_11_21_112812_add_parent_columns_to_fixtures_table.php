<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('fixtures', function (Blueprint $table) {
      // If your primary key is bigIncrements, use unsignedBigInteger
      $table->unsignedBigInteger('parent_fixture_id')
        ->nullable()
        ->after('match_nr');

      $table->unsignedBigInteger('loser_parent_fixture_id')
        ->nullable()
        ->after('parent_fixture_id');

      // Optional but nice: self-referencing FKs
      $table->foreign('parent_fixture_id')
        ->references('id')
        ->on('fixtures')
        ->nullOnDelete();

      $table->foreign('loser_parent_fixture_id')
        ->references('id')
        ->on('fixtures')
        ->nullOnDelete();
    });
  }

  public function down(): void
  {
    Schema::table('fixtures', function (Blueprint $table) {
      $table->dropForeign(['parent_fixture_id']);
      $table->dropForeign(['loser_parent_fixture_id']);
      $table->dropColumn(['parent_fixture_id', 'loser_parent_fixture_id']);
    });
  }
};

