<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camp_configurations', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('event_id')->unique(); $t->string('method')->default('open');
            $t->timestamp('registration_closes_at')->nullable(); $t->timestamp('coach_closes_at')->nullable();
            $t->unsignedTinyInteger('coach_min_age')->default(8); $t->unsignedTinyInteger('coach_max_age')->default(9);
            $t->unsignedInteger('capacity')->nullable(); $t->json('divisions'); $t->uuid('revision');
            $t->unsignedBigInteger('updated_by'); $t->timestamps();
        });
        Schema::create('camp_nominees', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('category_event_id'); $t->unsignedBigInteger('player_id');
            $t->string('status')->default('approved'); $t->text('provenance')->nullable(); $t->unsignedBigInteger('approved_by');
            $t->timestamp('approved_at'); $t->string('token_hash', 64)->unique(); $t->text('token_encrypted');
            $t->timestamp('declined_at')->nullable(); $t->unsignedBigInteger('declined_by')->nullable();
            $t->unique(['event_id', 'category_event_id', 'player_id'], 'camp_nominee_identity'); $t->timestamps();
        });
        Schema::create('camp_coach_links', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('event_id'); $t->string('coach_name'); $t->string('token_hash', 64)->unique();
            $t->timestamp('expires_at'); $t->timestamp('revoked_at')->nullable(); $t->unsignedBigInteger('created_by'); $t->timestamps();
        });
        Schema::create('camp_coach_submissions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('coach_link_id'); $t->uuid('request_token');
            $t->string('name'); $t->string('surname'); $t->date('date_of_birth'); $t->unsignedTinyInteger('gender');
            $t->string('guardian_name'); $t->string('guardian_email'); $t->text('reason')->nullable();
            $t->string('status')->default('pending'); $t->unsignedBigInteger('player_id')->nullable();
            $t->unsignedBigInteger('nominee_id')->nullable(); $t->unsignedBigInteger('reviewed_by')->nullable();
            $t->timestamp('reviewed_at')->nullable(); $t->unique(['coach_link_id', 'request_token'], 'camp_coach_request'); $t->timestamps();
        });
        Schema::create('camp_mail_reviews', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('actor_id'); $t->json('snapshot');
            $t->string('fingerprint', 64); $t->timestamp('expires_at'); $t->timestamp('queued_at')->nullable(); $t->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['camp_mail_reviews', 'camp_coach_submissions', 'camp_coach_links', 'camp_nominees', 'camp_configurations'] as $table) Schema::dropIfExists($table);
    }
};
