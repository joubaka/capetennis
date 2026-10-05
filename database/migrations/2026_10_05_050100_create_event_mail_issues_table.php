<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_mail_issues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('log_id');
            $table->string('kind', 30);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['log_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_mail_issues');
    }
};
