<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('team_payment_orders')) {
            return;
        }

        $addWithdrawnAt = ! Schema::hasColumn('team_payment_orders', 'withdrawn_at');
        $addWithdrawnBy = ! Schema::hasColumn('team_payment_orders', 'withdrawn_by');

        if (! $addWithdrawnAt && ! $addWithdrawnBy) {
            return;
        }

        Schema::table('team_payment_orders', function (Blueprint $table) use ($addWithdrawnAt, $addWithdrawnBy): void {
            if ($addWithdrawnAt) {
                $table->timestamp('withdrawn_at')->nullable()->after('refund_net');
            }
            if ($addWithdrawnBy) {
                $table->unsignedBigInteger('withdrawn_by')->nullable()->after('withdrawn_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('team_payment_orders')) {
            return;
        }

        $columns = array_values(array_filter(
            ['withdrawn_at', 'withdrawn_by'],
            fn (string $column): bool => Schema::hasColumn('team_payment_orders', $column)
        ));

        if ($columns !== []) {
            Schema::table('team_payment_orders', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
