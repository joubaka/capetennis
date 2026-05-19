<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('category_event_registrations', 'withdrawn_by')) {
            Schema::table('category_event_registrations', function (Blueprint $table) {
                $table->unsignedBigInteger('withdrawn_by')->nullable()->after('withdrawn_at');
                $table->string('withdrawal_reason', 500)->nullable()->after('withdrawn_by');
            });
        }
    }

    public function down(): void
    {
        Schema::table('category_event_registrations', function (Blueprint $table) {
            $table->dropColumn(['withdrawn_by', 'withdrawal_reason']);
        });
    }
};
