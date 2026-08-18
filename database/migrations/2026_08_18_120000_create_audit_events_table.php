<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->char('event_uuid', 26)->unique();
            $table->timestamp('occurred_at', 6)->index();
            $table->string('category', 40)->index();
            $table->string('action', 120)->index();
            $table->string('outcome', 20)->default('succeeded')->index();
            $table->string('source', 20)->default('web')->index();

            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_type', 30)->default('user');
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable()->index();
            $table->json('actor_roles')->nullable();

            $table->string('subject_type', 120)->nullable()->index();
            $table->string('subject_id', 191)->nullable()->index();
            $table->string('subject_label')->nullable();
            $table->unsignedBigInteger('event_id')->nullable()->index();

            $table->string('request_id', 36)->nullable()->index();
            $table->string('journey_id', 64)->nullable()->index();
            $table->string('previous_request_id', 36)->nullable();
            $table->string('batch_id', 64)->nullable()->index();
            $table->string('route_name')->nullable()->index();
            $table->string('http_method', 10)->nullable();
            $table->text('path')->nullable();
            $table->text('referrer')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->text('reason')->nullable();
            $table->char('integrity_hash', 64);
            $table->timestamp('created_at', 6);

            $table->index(['subject_type', 'subject_id', 'occurred_at'], 'audit_subject_timeline_idx');
            $table->index(['actor_id', 'occurred_at'], 'audit_actor_timeline_idx');
            $table->index(['event_id', 'occurred_at'], 'audit_event_timeline_idx');
            $table->index(['category', 'occurred_at'], 'audit_category_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
