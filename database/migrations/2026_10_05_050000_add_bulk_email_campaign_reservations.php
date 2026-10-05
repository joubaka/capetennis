<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulk_email_logs', function (Blueprint $table) {
            $table->string('deduplication_key', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('bulk_email_logs', function (Blueprint $table) {
            $table->dropUnique(['deduplication_key']);
            $table->dropColumn('deduplication_key');
        });
    }
};
