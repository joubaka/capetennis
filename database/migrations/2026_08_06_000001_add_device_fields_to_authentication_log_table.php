<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('authentication-log.table_name', 'authentication_log');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        $columns = [
            'device_id' => fn (Blueprint $table) => $table->string('device_id')->nullable()->index()->after('user_agent'),
            'device_name' => fn (Blueprint $table) => $table->string('device_name')->nullable()->after('device_id'),
            'is_trusted' => fn (Blueprint $table) => $table->boolean('is_trusted')->default(false)->after('device_name'),
            'last_activity_at' => fn (Blueprint $table) => $table->timestamp('last_activity_at')->nullable()->after('logout_at'),
            'is_suspicious' => fn (Blueprint $table) => $table->boolean('is_suspicious')->default(false)->after('location'),
            'suspicious_reason' => fn (Blueprint $table) => $table->string('suspicious_reason')->nullable()->after('is_suspicious'),
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn($tableName, $column)) {
                Schema::table($tableName, $definition);
            }
        }
    }

    public function down(): void
    {
        $tableName = config('authentication-log.table_name', 'authentication_log');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        foreach (['suspicious_reason', 'is_suspicious', 'last_activity_at', 'is_trusted', 'device_name', 'device_id'] as $column) {
            if (Schema::hasColumn($tableName, $column)) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
