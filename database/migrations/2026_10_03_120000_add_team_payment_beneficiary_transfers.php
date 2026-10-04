<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_ACTIVE_UNIQUE = 'team_payment_orders_one_active_order_unique';

    private const EFFECTIVE_ACTIVE_UNIQUE = 'team_payment_orders_effective_active_unique';

    private const TRANSFER_ORDER_INDEX = 'team_payment_transfers_order_id_index';

    public function up(): void
    {
        if (! Schema::hasColumn('team_payment_orders', 'beneficiary_player_id')) {
            Schema::table('team_payment_orders', function (Blueprint $table): void {
                $table->unsignedBigInteger('beneficiary_player_id')->nullable();
            });
        }

        if (! Schema::hasColumn('team_payment_orders', 'effective_player_id')) {
            Schema::table('team_payment_orders', function (Blueprint $table): void {
                $table->unsignedBigInteger('effective_player_id')
                    ->nullable()
                    ->storedAs('COALESCE(beneficiary_player_id, player_id)');
            });
        }

        // MySQL commits DDL implicitly. Build the replacement constraint before
        // removing the legacy one so a failed run never leaves active orders
        // without a uniqueness guard, and every step can safely be retried.
        if (! Schema::hasIndex('team_payment_orders', self::EFFECTIVE_ACTIVE_UNIQUE)) {
            Schema::table('team_payment_orders', function (Blueprint $table): void {
                $table->unique(
                    ['team_id', 'effective_player_id', 'event_id', 'active_order_marker'],
                    self::EFFECTIVE_ACTIVE_UNIQUE,
                );
            });
        }

        if (Schema::hasIndex('team_payment_orders', self::LEGACY_ACTIVE_UNIQUE)) {
            Schema::table('team_payment_orders', function (Blueprint $table): void {
                $table->dropUnique(self::LEGACY_ACTIVE_UNIQUE);
            });
        }

        if (! Schema::hasTable('team_payment_transfers')) {
            Schema::create('team_payment_transfers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('event_id');
                $table->unsignedBigInteger('team_id');
                $table->unsignedBigInteger('original_player_id');
                $table->unsignedBigInteger('from_player_id');
                $table->unsignedBigInteger('to_player_id');
                $table->unsignedBigInteger('actor_id');
                $table->string('reason', 1000);
                $table->timestamp('created_at');
            });
        }

        if (! Schema::hasIndex('team_payment_transfers', self::TRANSFER_ORDER_INDEX)) {
            Schema::table('team_payment_transfers', function (Blueprint $table): void {
                $table->index('order_id', self::TRANSFER_ORDER_INDEX);
            });
        }
    }

    public function down(): void
    {
        $hasTransferHistory = Schema::hasTable('team_payment_transfers')
            && DB::table('team_payment_transfers')->exists();
        $hasAllocatedBeneficiary = Schema::hasColumn('team_payment_orders', 'beneficiary_player_id')
            && DB::table('team_payment_orders')->whereNotNull('beneficiary_player_id')->exists();

        if ($hasTransferHistory || $hasAllocatedBeneficiary) {
            throw new RuntimeException('Payment allocation history exists; this migration cannot be rolled back.');
        }
        if (DB::table('team_payment_orders')->whereNull('withdrawn_at')->select(['team_id', 'player_id', 'event_id'])
            ->groupBy(['team_id', 'player_id', 'event_id'])->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Active original-player order identities conflict; this migration cannot be rolled back.');
        }

        if (! Schema::hasIndex('team_payment_orders', self::LEGACY_ACTIVE_UNIQUE)) {
            Schema::table('team_payment_orders', function (Blueprint $table): void {
                $table->unique(
                    ['team_id', 'player_id', 'event_id', 'active_order_marker'],
                    self::LEGACY_ACTIVE_UNIQUE,
                );
            });
        }

        if (Schema::hasIndex('team_payment_orders', self::EFFECTIVE_ACTIVE_UNIQUE)) {
            Schema::table('team_payment_orders', function (Blueprint $table): void {
                $table->dropUnique(self::EFFECTIVE_ACTIVE_UNIQUE);
            });
        }

        Schema::dropIfExists('team_payment_transfers');

        if (Schema::hasColumn('team_payment_orders', 'effective_player_id')) {
            Schema::table('team_payment_orders', function (Blueprint $table): void {
                $table->dropColumn('effective_player_id');
            });
        }
        if (Schema::hasColumn('team_payment_orders', 'beneficiary_player_id')) {
            Schema::table('team_payment_orders', function (Blueprint $table): void {
                $table->dropColumn('beneficiary_player_id');
            });
        }
    }
};
