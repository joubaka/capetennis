<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plus_digest_preferences', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->boolean('enabled')->default(true);
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamp('resume_after')->nullable();
            $table->boolean('needs_baseline')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plus_digest_preferences');
    }
};
