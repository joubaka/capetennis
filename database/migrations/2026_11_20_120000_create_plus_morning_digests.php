<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plus_digest_sources', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->unsignedBigInteger('event_id')->index();
            $table->string('revision', 100);
            $table->json('snapshot');
            $table->timestamps();
        });
        Schema::create('plus_digest_changes', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->unsignedBigInteger('event_id')->index();
            $table->string('revision', 100);
            $table->string('kind');
            $table->json('snapshot');
            $table->timestamps();
            $table->index(['key', 'id']);
        });
        Schema::create('plus_digest_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->unsignedBigInteger('cursor')->default(0);
            $table->date('prepared_date')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('plus_digest_seen', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('key');
            $table->string('revision', 100);
            $table->timestamps();
            $table->unique(['user_id', 'key']);
        });
        Schema::create('plus_digest_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->date('digest_date');
            $table->string('recipient');
            $table->json('items');
            $table->unsignedBigInteger('end_cursor');
            $table->timestamp('queued_at')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'digest_date']);
        });
        Schema::create('plus_digest_runs', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedBigInteger('event_cursor')->default(0);
            $table->unsignedBigInteger('announcement_cursor')->default(0);
            $table->boolean('events_complete')->default(false);
            $table->boolean('announcements_complete')->default(false);
            $table->boolean('baselined')->default(false);
            $table->timestamp('scan_completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['plus_digest_runs', 'plus_digest_deliveries', 'plus_digest_seen', 'plus_digest_accounts', 'plus_digest_changes', 'plus_digest_sources'] as $table) Schema::dropIfExists($table);
    }
};
