<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $table) {
                $table->string('email')->index();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('series')) {
            Schema::create('series', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->integer('year')->nullable();
                $table->unsignedBigInteger('rank_type')->nullable();
                $table->boolean('leaderboard_published')->default(false);
                $table->integer('best_num_of_scores')->nullable();
                $table->boolean('points_template_created')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('event_admins')) {
            Schema::create('event_admins', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('withdrawals')) {
            Schema::create('withdrawals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('registration_id')->nullable();
                $table->unsignedBigInteger('category_event_id')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('event_admins');
        Schema::dropIfExists('series');
        Schema::dropIfExists('password_resets');
    }
};
