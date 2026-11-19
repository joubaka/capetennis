<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_nominations', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
            $table->unsignedBigInteger('archived_by')->nullable();
            $table->string('archive_reason', 1000)->nullable();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('event_nominations')->whereNotNull('archived_at')->exists()) {
            throw new RuntimeException('Retain archived nomination evidence before removing archive columns.');
        }
        Schema::table('event_nominations', fn (Blueprint $table) => $table->dropColumn(['archived_at', 'archived_by', 'archive_reason']));
    }
};
