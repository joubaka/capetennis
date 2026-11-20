<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('host_event_applications', fn (Blueprint $table) => $table->json('court_plan')->nullable());
    }

    public function down(): void
    {
        Schema::table('host_event_applications', fn (Blueprint $table) => $table->dropColumn('court_plan'));
    }
};
