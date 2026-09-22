<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SOURCE_PLAYER_ID = 2439;

    private const TARGET_PLAYER_ID = 5332;

    private const TARGET_USER_ID = 4025;

    /** @var array<int, array{event_id: int, event_name: string, category_event_id: int, registration_id: int, player_registration_id: int, category_entry_id: int, order_id: int, order_item_id: int, payer_user_id: ?int, paid: bool, item_price: float, order_total: float, order_status: ?string}> */
    private const ENTRIES = [
        [
            'event_id' => 230,
            'event_name' => 'Cavaliers Junior Wilson Paarl Tournament 2026',
            'category_event_id' => 1861,
            'registration_id' => 18445,
            'player_registration_id' => 16689,
            'category_entry_id' => 16877,
            'order_id' => 8411,
            'order_item_id' => 9435,
            'payer_user_id' => null,
            'paid' => false,
            'item_price' => 285.00,
            'order_total' => 0.00,
            'order_status' => 'pending',
        ],
        [
            'event_id' => 237,
            'event_name' => 'Cavaliers Junior Strand Tournament 2026',
            'category_event_id' => 2011,
            'registration_id' => 20410,
            'player_registration_id' => 18407,
            'category_entry_id' => 18627,
            'order_id' => 9997,
            'order_item_id' => 11283,
            'payer_user_id' => self::TARGET_USER_ID,
            'paid' => true,
            'item_price' => 285.00,
            'order_total' => 285.00,
            'order_status' => null,
        ],
    ];

    public function up(): void
    {
        foreach ([
            'players', 'events', 'category_events', 'registrations',
            'player_registrations', 'category_event_registrations',
            'registration_orders', 'registration_order_items',
            'series_rankings', 'masters_invitations', 'ranking_audit_logs',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Required table {$table} is missing; the audited Wilson Series identity correction was not applied.");
            }
        }

        // A brand-new installation has none of the audited production rows.
        // Once any incident sentinel exists, fail closed on every state guard.
        if (! DB::table('events')->whereIn('id', [230, 237])->exists()
            && ! DB::table('players')->whereIn('id', [self::SOURCE_PLAYER_ID, self::TARGET_PLAYER_ID])->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $this->assertPlayer(
                self::SOURCE_PLAYER_ID,
                'Dirk',
                'Coetzee',
                '2010-03-25',
                2340,
            );
            $this->assertPlayer(
                self::TARGET_PLAYER_ID,
                'Dirkie',
                'Coetzee',
                '2017-09-09',
                self::TARGET_USER_ID,
            );

            foreach (self::ENTRIES as $entry) {
                $this->assertEntryState($entry);
            }
            $ranking = $this->assertPublishedRankingBoundary();
            $this->assertMastersInvitationBoundary();

            foreach (self::ENTRIES as $entry) {
                DB::table('player_registrations')
                    ->where('id', $entry['player_registration_id'])
                    ->where('registration_id', $entry['registration_id'])
                    ->where('player_id', self::SOURCE_PLAYER_ID)
                    ->update([
                        'player_id' => self::TARGET_PLAYER_ID,
                        'updated_at' => now(),
                    ]);

                DB::table('registration_order_items')
                    ->where('id', $entry['order_item_id'])
                    ->where('order_id', $entry['order_id'])
                    ->where('registration_id', $entry['registration_id'])
                    ->where('player_id', self::SOURCE_PLAYER_ID)
                    ->update([
                        'player_id' => self::TARGET_PLAYER_ID,
                        'updated_at' => now(),
                    ]);
            }

            $this->recordReviewBoundary($ranking);
        });
    }

    /** @param array{event_id: int, event_name: string, category_event_id: int, registration_id: int, player_registration_id: int, category_entry_id: int, order_id: int, order_item_id: int, payer_user_id: ?int, paid: bool, item_price: float, order_total: float, order_status: ?string} $expected */
    private function assertEntryState(array $expected): void
    {
        $event = DB::table('events')->where('id', $expected['event_id'])->first(['name']);
        if (! $event || (string) $event->name !== $expected['event_name']) {
            throw new RuntimeException("Event {$expected['event_id']} is not the expected audited Series event.");
        }

        $categoryEvent = DB::table('category_events')
            ->where('id', $expected['category_event_id'])
            ->where('event_id', $expected['event_id'])
            ->first(['id']);
        $categoryEntry = DB::table('category_event_registrations')
            ->where('id', $expected['category_entry_id'])
            ->where('registration_id', $expected['registration_id'])
            ->where('category_event_id', $expected['category_event_id'])
            ->where('user_id', self::TARGET_USER_ID)
            ->where('status', 'active')
            ->where('payment_status_id', 1)
            ->lockForUpdate()
            ->first(['id']);

        if (! $categoryEvent || ! $categoryEntry || ! DB::table('registrations')->where('id', $expected['registration_id'])->exists()) {
            throw new RuntimeException("Registration {$expected['registration_id']} no longer matches the audited Wilson Series entry.");
        }

        $playerRegistration = DB::table('player_registrations')
            ->where('id', $expected['player_registration_id'])
            ->where('registration_id', $expected['registration_id'])
            ->lockForUpdate()
            ->first(['player_id']);
        if (! $playerRegistration || ! in_array((int) $playerRegistration->player_id, [self::SOURCE_PLAYER_ID, self::TARGET_PLAYER_ID], true)) {
            throw new RuntimeException("Player registration {$expected['player_registration_id']} no longer matches the audited identity state.");
        }
        if (DB::table('player_registrations')
            ->where('registration_id', $expected['registration_id'])
            ->where('player_id', self::TARGET_PLAYER_ID)
            ->where('id', '<>', $expected['player_registration_id'])
            ->exists()) {
            throw new RuntimeException("Registration {$expected['registration_id']} already has a conflicting Dirkie Coetzee link.");
        }

        $item = DB::table('registration_order_items')
            ->where('id', $expected['order_item_id'])
            ->where('order_id', $expected['order_id'])
            ->where('registration_id', $expected['registration_id'])
            ->where('category_event_id', $expected['category_event_id'])
            ->where('user_id', self::TARGET_USER_ID)
            ->lockForUpdate()
            ->first(['player_id', 'item_price']);
        $orderQuery = DB::table('registration_orders')
            ->where('id', $expected['order_id'])
            ->lockForUpdate();
        if ($expected['payer_user_id'] === null) {
            $orderQuery->whereNull('user_id');
        } else {
            $orderQuery->where('user_id', $expected['payer_user_id']);
        }
        $order = $orderQuery->first(['pay_status', 'payfast_paid', 'total_fee', 'status']);

        if (! $item
            || ! in_array((int) $item->player_id, [self::SOURCE_PLAYER_ID, self::TARGET_PLAYER_ID], true)
            || ! $order
            || (bool) $order->pay_status !== $expected['paid']
            || (bool) $order->payfast_paid !== $expected['paid']
            || round((float) $order->total_fee, 2) !== $expected['order_total']
            || ($expected['order_status'] !== null && (string) $order->status !== $expected['order_status'])
            || round((float) $item->item_price, 2) !== $expected['item_price']) {
            throw new RuntimeException("Order item {$expected['order_item_id']} no longer matches the audited ownership and payment state.");
        }
    }

    private function assertPlayer(int $id, string $name, string $surname, string $dateOfBirth, int $userId): void
    {
        $player = DB::table('players')->where('id', $id)->lockForUpdate()->first([
            'name', 'surname', 'dateOfBirth', 'userId',
        ]);
        if (! $player
            || strcasecmp(trim((string) $player->name), $name) !== 0
            || strcasecmp(trim((string) $player->surname), $surname) !== 0
            || (string) $player->dateOfBirth !== $dateOfBirth
            || (int) $player->userId !== $userId) {
            throw new RuntimeException("Player {$id} no longer matches the audited Dirk Coetzee identity evidence.");
        }
    }

    private function assertPublishedRankingBoundary(): object
    {
        $ranking = DB::table('series_rankings')
            ->where('series_id', 18)
            ->where('ranking_list_id', 938)
            ->where('player_id', self::SOURCE_PLAYER_ID)
            ->where('rank_position', 8)
            ->where('total_points', 80)
            ->whereIn('status', ['published', 'archived'])
            ->orderByRaw("CASE WHEN status = 'published' THEN 0 ELSE 1 END")
            ->lockForUpdate()
            ->first(['id', 'run_id', 'status']);

        if (! $ranking) {
            throw new RuntimeException('The published Wilson Series ranking no longer matches the audited Dirk Coetzee state.');
        }
        if (DB::table('series_rankings')
            ->where('series_id', 18)
            ->where('ranking_list_id', 938)
            ->where('player_id', self::TARGET_PLAYER_ID)
            ->exists()) {
            throw new RuntimeException('Dirkie Coetzee already has a Wilson Series ranking snapshot; manual review is required.');
        }

        return $ranking;
    }

    private function assertMastersInvitationBoundary(): void
    {
        $invitation = DB::table('masters_invitations')
            ->where('id', 807)
            ->where('batch_id', 4)
            ->where('event_id', 254)
            ->where('category_event_id', 2182)
            ->where('player_id', self::SOURCE_PLAYER_ID)
            ->where('ranking_position', 8)
            ->where('total_points', 80)
            ->where('status', 'declined')
            ->whereNull('registration_id')
            ->whereNull('order_id')
            ->lockForUpdate()
            ->first(['id']);

        if (! $invitation) {
            throw new RuntimeException('Masters invitation 807 no longer matches the audited declined, unpaid state.');
        }
        if (DB::table('masters_invitations')
            ->where('batch_id', 4)
            ->where('player_id', self::TARGET_PLAYER_ID)
            ->exists()) {
            throw new RuntimeException('Dirkie Coetzee already has a Masters invitation in batch 4; manual review is required.');
        }
    }

    private function recordReviewBoundary(object $ranking): void
    {
        $key = [
            'series_id' => 18,
            'run_id' => $ranking->run_id,
            'action' => 'player_identity_correction_requires_review',
        ];
        if (DB::table('ranking_audit_logs')->where($key)->exists()) {
            return;
        }

        DB::table('ranking_audit_logs')->insert($key + [
            'payload' => json_encode([
                'source_player_id' => self::SOURCE_PLAYER_ID,
                'target_player_id' => self::TARGET_PLAYER_ID,
                'corrected_registration_ids' => [18445, 20410],
                'ranking_snapshot_id' => (int) $ranking->id,
                'ranking_snapshot_status' => (string) $ranking->status,
                'masters_invitation_id' => 807,
                'masters_invitation_status' => 'declined',
                'boundary' => 'Published rankings and declined invitation 807 were not changed. Rebuild, review, and publish Dirkie 5332 in the canonical ranking workflow before running the confirmation-gated Masters identity-replacement command.',
            ], JSON_THROW_ON_ERROR),
            'user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // This is an audited identity correction. Reversal must not silently
        // restore the incorrect player to historical entries or order items.
    }
};
