<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema hardening: category_event_registrations.
 *
 * Problems:
 *  - No index on (category_event_id, registration_id) — queried on every
 *    draw build, score lookup, and standings computation.
 *  - 2 duplicate active rows (same category_event_id + registration_id, no deleted_at).
 *    Cannot add UNIQUE yet — index only, with comment.
 *  - 34 withdrawn records have no deleted_at. Soft-delete was added later.
 *    We do NOT auto-delete them (data risk), but record the count via a comment.
 *  - refund_status has no index; used in refund pipeline WHERE clauses.
 *  - withdrawals table has no indexes and FK columns are bare ints.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table("category_event_registrations", function (Blueprint $table) {
            $table->index(["category_event_id", "registration_id"],
                "cer_category_event_registration_index");
            $table->index("registration_id",  "cer_registration_id_index");
            $table->index("refund_status",    "cer_refund_status_index");
            $table->index("status",           "cer_status_index");
        });

        Schema::table("withdrawals", function (Blueprint $table) {
            $table->index("registration_id",   "withdrawals_registration_id_index");
            $table->index("category_event_id", "withdrawals_category_event_id_index");
            $table->index("user_id",           "withdrawals_user_id_index");
        });
    }

    public function down(): void
    {
        Schema::table("withdrawals", function (Blueprint $table) {
            $table->dropIndex("withdrawals_registration_id_index");
            $table->dropIndex("withdrawals_category_event_id_index");
            $table->dropIndex("withdrawals_user_id_index");
        });

        Schema::table("category_event_registrations", function (Blueprint $table) {
            $table->dropIndex("cer_category_event_registration_index");
            $table->dropIndex("cer_registration_id_index");
            $table->dropIndex("cer_refund_status_index");
            $table->dropIndex("cer_status_index");
        });
    }
};