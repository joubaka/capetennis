<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_daily_seals', function (Blueprint $table) {
            $table->id();
            $table->date('audit_date')->unique();
            $table->unsignedBigInteger('event_count');
            $table->unsignedBigInteger('first_event_id')->nullable();
            $table->unsignedBigInteger('last_event_id')->nullable();
            $table->char('digest', 64);
            $table->timestamp('sealed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_daily_seals');
    }
};
