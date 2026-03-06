<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('event_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->string('expense_type', 50); // balls, venue, convenors, data, petrol, accommodation, cape_tennis_fee, payfast, other
            $table->string('convenor_name', 100)->nullable(); // Name of convenor (for convenor-related expenses)
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 10, 2);
            $table->date('date')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'expense_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_expenses');
    }
};
