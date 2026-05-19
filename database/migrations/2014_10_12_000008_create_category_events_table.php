<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('category_events')) {
            return;
        }

        Schema::create('category_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->decimal('entry_fee', 8, 2)->nullable();
            $table->integer('ordering')->nullable();
            $table->boolean('nominations_published')->default(false);
            $table->timestamp('locked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_events');
    }
};
