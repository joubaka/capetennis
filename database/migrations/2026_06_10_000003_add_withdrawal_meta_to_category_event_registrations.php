<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('category_event_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('category_event_registrations', 'withdrawn_by')) {
                $table->unsignedBigInteger('withdrawn_by')->nullable()->after('withdrawn_at');
            }
            if (! Schema::hasColumn('category_event_registrations', 'withdrawal_reason')) {
                $table->string('withdrawal_reason')->nullable()->after('withdrawn_by');
            }
        });
    }

    public function down(): void {
        Schema::table('category_event_registrations', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('category_event_registrations', 'withdrawal_reason')) $cols[] = 'withdrawal_reason';
            if (Schema::hasColumn('category_event_registrations', 'withdrawn_by')) $cols[] = 'withdrawn_by';
            if ($cols) $table->dropColumn($cols);
        });
    }
};

