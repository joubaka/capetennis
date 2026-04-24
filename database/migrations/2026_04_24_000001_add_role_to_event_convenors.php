<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_convenors', function (Blueprint $table) {
            // hoof = Head convenor, hulp = Helper convenor, admin = Admin only
            $table->string('role', 20)->default('hoof')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_convenors', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
