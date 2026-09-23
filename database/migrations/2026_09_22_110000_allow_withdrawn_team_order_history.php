<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACTIVE_COLUMN = 'active_order_marker';
    private const ACTIVE_UNIQUE = 'team_payment_orders_one_active_order_unique';

    public function up(): void
    {
        DB::table('team_selection_invitations')
            ->whereNotNull('snapshot_json')
            ->orderBy('id')
            ->chunkById(200, function ($invitations): void {
                foreach ($invitations as $invitation) {
                    $snapshot = json_decode((string) $invitation->snapshot_json, true);
                    $legacyId = data_get($snapshot, 'restoration.previous_order_id');
                    $historyIds = data_get($snapshot, 'restoration.previous_order_ids', []);
                    $orderIds = collect(is_array($historyIds) ? $historyIds : [])
                        ->push($legacyId)->filter()->map(fn ($id): int => (int) $id)->unique();
                    if ($orderIds->isEmpty()) {
                        continue;
                    }

                    foreach ($orderIds as $orderId) {
                        $order = DB::table('team_payment_orders')->where('id', $orderId)->first([
                            'id', 'team_id', 'player_id', 'event_id', 'withdrawn_at',
                        ]);
                        if (! $order
                            || (int) $order->team_id !== (int) $invitation->team_id
                            || (int) $order->player_id !== (int) $invitation->player_id
                            || (int) $order->event_id !== (int) $invitation->event_id) {
                            throw new RuntimeException(
                                "Restoration history order {$orderId} does not match invitation {$invitation->id}."
                            );
                        }

                        if ($order->withdrawn_at === null) {
                            DB::table('team_payment_orders')
                                ->where('id', $orderId)
                                ->where('team_id', $invitation->team_id)
                                ->where('player_id', $invitation->player_id)
                                ->where('event_id', $invitation->event_id)
                                ->whereNull('withdrawn_at')
                                ->update(['withdrawn_at' => $invitation->declined_at ?: $invitation->updated_at ?: now()]);
                        }
                    }
                }
            });

        if (! Schema::hasColumn('team_payment_orders', self::ACTIVE_COLUMN)) {
            Schema::table('team_payment_orders', function (Blueprint $table): void {
                $table->unsignedTinyInteger(self::ACTIVE_COLUMN)
                    ->nullable()
                    ->storedAs('CASE WHEN `withdrawn_at` IS NULL THEN 1 ELSE NULL END');
            });
        }

        $indexes = collect(Schema::getIndexes('team_payment_orders'));
        if (! $indexes->contains(fn (array $index): bool => ($index['name'] ?? null) === self::ACTIVE_UNIQUE)) {
            Schema::table('team_payment_orders', function (Blueprint $table): void {
                $table->unique(
                    ['team_id', 'player_id', 'event_id', self::ACTIVE_COLUMN],
                    self::ACTIVE_UNIQUE,
                );
            });
        }

        $legacyIndexes = collect(Schema::getIndexes('team_payment_orders'))
            ->filter(fn (array $index): bool => (bool) ($index['unique'] ?? false)
                && ($index['name'] ?? null) !== self::ACTIVE_UNIQUE
                && array_values($index['columns'] ?? []) === ['team_id', 'player_id', 'event_id']);

        foreach ($legacyIndexes as $index) {
            Schema::table('team_payment_orders', fn (Blueprint $table) => $table->dropUnique($index['name']));
        }
    }

    public function down(): void
    {
        $hasLifetimeDuplicates = DB::table('team_payment_orders')
            ->select(['team_id', 'player_id', 'event_id'])
            ->groupBy(['team_id', 'player_id', 'event_id'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasLifetimeDuplicates) {
            throw new RuntimeException('Cannot restore the legacy team-order uniqueness constraint while historical order lifecycles exist.');
        }

        $indexes = collect(Schema::getIndexes('team_payment_orders'));
        $hasLegacy = $indexes->contains(fn (array $index): bool => (bool) ($index['unique'] ?? false)
            && array_values($index['columns'] ?? []) === ['team_id', 'player_id', 'event_id']);
        if (! $hasLegacy) {
            Schema::table('team_payment_orders', function (Blueprint $table): void {
                $table->unique(['team_id', 'player_id', 'event_id'], 'unique_team_player_event');
            });
        }

        $indexes = collect(Schema::getIndexes('team_payment_orders'));
        if ($indexes->contains(fn (array $index): bool => ($index['name'] ?? null) === self::ACTIVE_UNIQUE)) {
            Schema::table('team_payment_orders', fn (Blueprint $table) => $table->dropUnique(self::ACTIVE_UNIQUE));
        }
        if (Schema::hasColumn('team_payment_orders', self::ACTIVE_COLUMN)) {
            Schema::table('team_payment_orders', fn (Blueprint $table) => $table->dropColumn(self::ACTIVE_COLUMN));
        }
    }
};
