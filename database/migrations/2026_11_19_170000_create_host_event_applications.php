<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('host_event_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->uuid('submission_token')->unique();
            $table->string('event_name');
            $table->string('academy');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone', 40);
            $table->string('town');
            $table->string('venue');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('courts');
            $table->json('requested_categories');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->unsignedBigInteger('event_id')->nullable()->unique();
            $table->timestamps();
        });
        Schema::create('host_event_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('admin_id');
            $table->string('status', 30)->default('queued');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['application_id', 'admin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('host_event_notifications');
        Schema::dropIfExists('host_event_applications');
    }
};
