<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_income_items')) {
            return;
        }

        Schema::create('event_income_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->string('label', 255);                      // e.g. "CT Entry fees"
            $table->decimal('quantity', 10, 2)->nullable();    // e.g. 48
            $table->decimal('unit_price', 10, 2)->nullable();  // e.g. 50.00
            $table->decimal('total', 10, 2)->default(0);       // stored total (qty × price or manual)
            $table->string('source', 255)->nullable();         // e.g. "invoice CT"
            $table->date('date')->nullable();
            $table->timestamps();

            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_income_items');
    }
};
