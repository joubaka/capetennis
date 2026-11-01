<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teamsheet_tennis_feeds', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('origin', 500)->nullable();
            $table->string('snapshot_hash', 64)->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamp('last_success_at')->nullable();
        });
        \Illuminate\Support\Facades\DB::table('teamsheet_tennis_feeds')->insert(['id' => 1]);
        Schema::create('teamsheet_tennis_competitions', function (Blueprint $table) {
            $table->id();
            $table->string('source_key', 100)->unique();
        });
        Schema::create('teamsheet_tennis_player_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teamsheet_player_id')->unique();
            $table->unsignedBigInteger('player_id')->unique();
            $table->unsignedBigInteger('reviewed_by');
            $table->string('reason', 1000);
            $table->timestamps();
        });
        Schema::create('teamsheet_tennis_matches', function (Blueprint $table) {
            $table->id();
            $table->string('source_match_id', 100)->unique();
            $table->string('source_version', 64);
            $table->unsignedBigInteger('competition_id');
            // Preserve the signed wire key order (MySQL JSON normalizes object keys).
            $table->longText('payload');
            $table->boolean('retracted')->default(false)->index();
            $table->date('reviewed_played_on')->nullable();
            $table->string('reviewed_version', 64)->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('review_reason', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teamsheet_tennis_matches');
        Schema::dropIfExists('teamsheet_tennis_player_links');
        Schema::dropIfExists('teamsheet_tennis_competitions');
        Schema::dropIfExists('teamsheet_tennis_feeds');
    }
};
