<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interprovincial_trial_invitation_batches', function (Blueprint $table): void {
            $table->string('email_subject', 150)->nullable();
            $table->text('email_body')->nullable();
            $table->string('message_hash', 64)->nullable();
            $table->string('reviewed_message_hash', 64)->nullable();
            $table->unsignedInteger('content_version')->default(0);
            $table->timestamp('prepared_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('interprovincial_trial_invitation_batches', function (Blueprint $table): void {
            $table->dropColumn([
                'email_subject',
                'email_body',
                'message_hash',
                'reviewed_message_hash',
                'content_version',
                'prepared_at',
            ]);
        });
    }
};
