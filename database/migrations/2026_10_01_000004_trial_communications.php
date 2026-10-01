<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('trial_message_templates', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('event_id')->index(); $t->unsignedBigInteger('created_by'); $t->string('name'); $t->string('subject'); $t->text('body'); $t->timestamps(); });
        Schema::create('trial_mail_previews', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('event_id')->index(); $t->unsignedBigInteger('actor_id'); $t->uuid('token')->unique(); $t->json('options'); $t->string('subject'); $t->text('body'); $t->json('recipients'); $t->json('excluded'); $t->string('snapshot_hash',64); $t->timestamp('expires_at'); $t->timestamp('committed_at')->nullable(); $t->timestamps(); });
        Schema::create('trial_mail_schedules', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('event_id')->index(); $t->unsignedBigInteger('actor_id'); $t->json('options'); $t->string('subject'); $t->text('body'); $t->timestamp('next_send_at')->index(); $t->unsignedInteger('repeat_hours')->nullable(); $t->timestamp('stop_at')->nullable(); $t->boolean('active')->default(true); $t->timestamps(); });
    }
    public function down(): void {
        $tables = ['trial_mail_schedules', 'trial_mail_previews', 'trial_message_templates'];
        foreach ($tables as $name) {
            if (Schema::hasTable($name) && \Illuminate\Support\Facades\DB::table($name)->exists()) {
                throw new \RuntimeException('Preserve Trials communication approvals and history before rolling back.');
            }
        }
        foreach ($tables as $name) Schema::dropIfExists($name);
    }
};
