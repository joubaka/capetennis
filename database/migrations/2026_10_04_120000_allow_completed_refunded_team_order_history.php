<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->changeMarker('CASE WHEN withdrawn_at IS NULL AND (refund_status IS NULL OR refund_status <> \'completed\') THEN 1 ELSE NULL END');
    }

    public function down(): void
    {
        if (DB::table('team_payment_orders')->whereNull('withdrawn_at')->select(['team_id', 'effective_player_id', 'event_id'])
            ->groupBy(['team_id', 'effective_player_id', 'event_id'])->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot restore the old active-order constraint while completed refund history and a new active checkout coexist.');
        }
        $this->changeMarker('CASE WHEN withdrawn_at IS NULL THEN 1 ELSE NULL END');
    }

    private function changeMarker(string $expression): void
    {
        // Keep the existing unique index throughout the atomic table alteration.
        Schema::table('team_payment_orders', function (Blueprint $table) use ($expression): void {
            $table->unsignedTinyInteger('active_order_marker')->nullable()->storedAs($expression)->change();
        });
    }
};
