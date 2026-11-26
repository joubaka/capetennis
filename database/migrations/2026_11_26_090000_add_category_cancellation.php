<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('category_events', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable();
            $table->json('cancellation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('category_events', fn (Blueprint $table) => $table->dropColumn(['cancelled_at', 'cancellation']));
    }
};
