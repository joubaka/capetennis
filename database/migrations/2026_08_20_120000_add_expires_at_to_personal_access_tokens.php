<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')
            || Schema::hasColumn('personal_access_tokens', 'expires_at')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->index()->after('last_used_at');
        });
    }

    public function down(): void
    {
        // Intentionally irreversible. Current Sanctum installations create this
        // column in the base table migration, so removing it could break tokens
        // on installations where this compatibility migration was a no-op.
    }
};
