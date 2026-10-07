<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('player_ability_snapshots', function (Blueprint $table) {
            $table->string('snapshot_key')->primary();
            $table->unsignedInteger('model_version');
            $table->string('policy_hash', 64);
            $table->date('as_of');
            $table->string('built_at');
            $table->string('source_fingerprint', 64);
            $table->string('publication_fingerprint', 64);
            $table->json('publication_manifest');
            $table->json('weighting_rules');
            $table->longText('payload');
            $table->timestamp('updated_at')->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('player_ability_snapshots'); }
};
