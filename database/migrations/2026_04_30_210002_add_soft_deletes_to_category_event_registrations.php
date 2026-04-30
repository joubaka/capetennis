<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds soft-delete support to category_event_registrations.
 * Allows records to be hidden from normal queries while preserving
 * data for audit / GDPR compliance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_event_registrations', function (Blueprint $table) {
            $table->softDeletes()->after('refund_account_type');
        });
    }

    public function down(): void
    {
        Schema::table('category_event_registrations', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
