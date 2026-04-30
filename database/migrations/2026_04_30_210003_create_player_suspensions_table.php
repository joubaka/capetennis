<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_suspensions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_id');
            $table->date('triggered_at');
            $table->unsignedTinyInteger('suspension_number')->default(1);
            $table->unsignedInteger('duration_months');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->text('notes')->nullable();
            $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lifted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_suspensions');
    }
};
