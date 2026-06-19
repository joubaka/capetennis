<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bulk_email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('mail_type')->index(); // e.g., 'tournament_announcement', 'bulk_event_mail'
            $table->string('related_type')->nullable()->index(); // e.g., 'App\Models\Announcement'
            $table->unsignedBigInteger('related_id')->nullable()->index(); // e.g., announcement ID
            $table->string('recipient_email')->index();
            $table->string('recipient_name')->nullable();
            $table->string('status')->default('queued')->index(); // queued, sent, failed, skipped
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable(); // additional data needed to rebuild the email
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();

            // Composite index for duplicate detection
            $table->index(['mail_type', 'related_type', 'related_id', 'recipient_email'], 'bulk_email_duplicate_check');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bulk_email_logs');
    }
};
