<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Draw;
use App\Models\DrawGroup;
use App\Models\DrawGroupRegistration;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\PilotEvent;
use App\Models\Player;
use App\Models\Registration;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * PilotEventSeeder
 *
 * Creates 4 isolated internal-pilot test events:
 *   1. rr         — Round-Robin with canonical engine enabled on its draw
 *   2. playoff    — Playoff bracket in hybrid mode (default)
 *   3. consolation — Feed-in/consolation hybrid draw
 *   4. payment    — Fake payment / withdrawal / refund scenarios
 *
 * All data is tagged [PILOT] in the event name and uses fake players.
 * Safe to run multiple times (uses firstOrCreate for the admin user).
 *
 * Usage:
 *   php artisan db:seed --class=PilotEventSeeder
 */
class PilotEventSeeder extends Seeder
{
    // Number of fake players per event
    private const PLAYER_COUNT = 8;

    public function run(): void
    {
        // Ensure required roles exist
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);

        // Admin user for all pilot actions
        $admin = User::firstOrCreate(
            ['email' => 'pilot-admin@internal.capetennis.test'],
            [
                'name'     => 'Pilot Admin',
                'password' => bcrypt('pilot-secret-2026'),
            ]
        );
        $admin->assignRole('admin');

        DB::transaction(function () use ($admin) {
            $this->seedRREvent($admin);
            $this->seedPlayoffEvent($admin);
            $this->seedConsolationEvent($admin);
            $this->seedPaymentEvent($admin);
        });

        $this->command->info('[PilotSeeder] All 4 pilot events seeded successfully.');
    }

    // ------------------------------------------------------------------
    // SCENARIO 1 — Round Robin (canonical engine)
    // ------------------------------------------------------------------

    private function seedRREvent(User $admin): void
    {
        $event = Event::factory()->create([
            'name'               => '[PILOT] RR Canonical Test ' . now()->format('Ymd-His'),
            'information'        => 'Internal RR pilot — canonical engine.',
            'email'              => 'pilot@internal.capetennis.test',
            'entryFee'           => 0,
            'published'          => false,
            'signUp'             => false,
            'engine_mode'        => 'hybrid',
        ]);

        $category    = Category::create(['name' => '[PILOT] Open Singles', 'Fee' => 0]);
        $catEvent    = CategoryEvent::create([
            'event_id'    => $event->id,
            'category_id' => $category->id,
            'entry_fee'   => 0,
            'ordering'    => 1,
        ]);

        // Create fake players + registrations
        $registrationIds = $this->createFakePlayers($catEvent, $admin, self::PLAYER_COUNT);

        // Create RR draw with canonical engine_mode override
        $draw = Draw::create([
            'drawName'           => '[PILOT] RR Main Draw',
            'event_id'           => $event->id,
            'category_event_id'  => $catEvent->id,
            'locked'             => false,
            'published'          => false,
            'engine_mode'        => 'canonical',  // ← pilot canonical override
        ]);

        // Create 2 RR groups of 4
        $this->createRRGroups($draw, $registrationIds);

        // Tag as pilot
        PilotEvent::create([
            'event_id'     => $event->id,
            'scenario'     => PilotEvent::SCENARIO_RR,
            'engine_mode'  => 'canonical',
            'player_count' => self::PLAYER_COUNT,
            'draw_count'   => 1,
            'status'       => PilotEvent::STATUS_ACTIVE,
            'notes'        => ['draw_id' => $draw->id, 'category_event_id' => $catEvent->id],
        ]);

        $this->command->info("  [RR]         event #{$event->id}  draw #{$draw->id}");
    }

    // ------------------------------------------------------------------
    // SCENARIO 2 — Playoff (hybrid default)
    // ------------------------------------------------------------------

    private function seedPlayoffEvent(User $admin): void
    {
        $event = Event::factory()->create([
            'name'               => '[PILOT] Playoff Hybrid Test ' . now()->format('Ymd-His'),
            'information'        => 'Internal playoff pilot — hybrid engine.',
            'email'              => 'pilot@internal.capetennis.test',
            'entryFee'           => 0,
            'published'          => false,
            'signUp'             => false,
            'engine_mode'        => 'hybrid',
        ]);

        $category = Category::create(['name' => '[PILOT] Playoff Singles', 'Fee' => 0]);
        $catEvent = CategoryEvent::create([
            'event_id'    => $event->id,
            'category_id' => $category->id,
            'entry_fee'   => 0,
            'ordering'    => 1,
        ]);

        $registrationIds = $this->createFakePlayers($catEvent, $admin, self::PLAYER_COUNT);

        // 8-player main draw (MAIN stage)
        $draw = Draw::create([
            'drawName'           => '[PILOT] Playoff Main Draw',
            'event_id'           => $event->id,
            'category_event_id'  => $catEvent->id,
            'locked'             => false,
            'published'          => false,
            'engine_mode'        => 'hybrid',
        ]);

        // Build a minimal 4-fixture first-round bracket
        $this->createPlayoffFixtures($draw, $registrationIds);

        PilotEvent::create([
            'event_id'     => $event->id,
            'scenario'     => PilotEvent::SCENARIO_PLAYOFF,
            'engine_mode'  => 'hybrid',
            'player_count' => self::PLAYER_COUNT,
            'draw_count'   => 1,
            'status'       => PilotEvent::STATUS_ACTIVE,
            'notes'        => ['draw_id' => $draw->id],
        ]);

        $this->command->info("  [Playoff]    event #{$event->id}  draw #{$draw->id}");
    }

    // ------------------------------------------------------------------
    // SCENARIO 3 — Feed-in / consolation (hybrid)
    // ------------------------------------------------------------------

    private function seedConsolationEvent(User $admin): void
    {
        $event = Event::factory()->create([
            'name'               => '[PILOT] Consolation Test ' . now()->format('Ymd-His'),
            'information'        => 'Internal consolation/feed-in pilot.',
            'email'              => 'pilot@internal.capetennis.test',
            'entryFee'           => 0,
            'published'          => false,
            'signUp'             => false,
            'engine_mode'        => 'hybrid',
        ]);

        $category = Category::create(['name' => '[PILOT] Consolation Singles', 'Fee' => 0]);
        $catEvent = CategoryEvent::create([
            'event_id'    => $event->id,
            'category_id' => $category->id,
            'entry_fee'   => 0,
            'ordering'    => 1,
        ]);

        // 6 players — leaves 2 BYE slots in a bracket of 8
        $playerCount     = 6;
        $registrationIds = $this->createFakePlayers($catEvent, $admin, $playerCount);

        // Main draw with BYE fixtures
        $mainDraw = Draw::create([
            'drawName'           => '[PILOT] Consolation Main',
            'event_id'           => $event->id,
            'category_event_id'  => $catEvent->id,
            'locked'             => false,
            'published'          => false,
            'engine_mode'        => 'hybrid',
        ]);

        // Consolation draw (separate draw, separate engine)
        $consolationDraw = Draw::create([
            'drawName'           => '[PILOT] Consolation Plate',
            'event_id'           => $event->id,
            'category_event_id'  => $catEvent->id,
            'locked'             => false,
            'published'          => false,
            'engine_mode'        => 'hybrid',
        ]);

        $this->createPlayoffFixtures($mainDraw, $registrationIds, includeBye: true);

        PilotEvent::create([
            'event_id'     => $event->id,
            'scenario'     => PilotEvent::SCENARIO_CONSOLATION,
            'engine_mode'  => 'hybrid',
            'player_count' => $playerCount,
            'draw_count'   => 2,
            'status'       => PilotEvent::STATUS_ACTIVE,
            'notes'        => [
                'main_draw_id'        => $mainDraw->id,
                'consolation_draw_id' => $consolationDraw->id,
            ],
        ]);

        $this->command->info("  [Consolation] event #{$event->id}  draws #{$mainDraw->id}/#{$consolationDraw->id}");
    }

    // ------------------------------------------------------------------
    // SCENARIO 4 — Payment / withdrawal / refund
    // ------------------------------------------------------------------

    private function seedPaymentEvent(User $admin): void
    {
        $event = Event::factory()->create([
            'name'               => '[PILOT] Payment & Refund Test ' . now()->format('Ymd-His'),
            'information'        => 'Internal payment / refund pilot.',
            'email'              => 'pilot@internal.capetennis.test',
            'entryFee'           => 150.00,
            'published'          => false,
            'signUp'             => false,
        ]);

        $category = Category::create(['name' => '[PILOT] Payment Singles', 'Fee' => 150.00]);
        $catEvent = CategoryEvent::create([
            'event_id'    => $event->id,
            'category_id' => $category->id,
            'entry_fee'   => 150.00,
            'ordering'    => 1,
        ]);

        $playerCount = 6;
        $users       = [];

        for ($i = 0; $i < $playerCount; $i++) {
            $user   = User::factory()->create();
            $player = Player::factory()->create(['userId' => $user->id]);

            // Seed fake wallet with credit balance
            $wallet = Wallet::factory()->forUser($user)->create();
            WalletTransaction::factory()->credit()->create([
                'wallet_id'   => $wallet->id,
                'amount'      => 200.00,
                'source_type' => 'pilot_seed',
                'source_id'   => $event->id,
                'meta'        => ['note' => 'pilot fake credit'],
            ]);

            $registration = Registration::create([]);
            $registration->players()->attach($player->id);

            // Vary the payment / withdrawal state
            $state = match ($i) {
                0 => ['status' => 'active',    'pf_transaction_id' => 'PILOT-PAY-001', 'payment_status_id' => 1],
                1 => ['status' => 'active',    'pf_transaction_id' => 'PILOT-PAY-002', 'payment_status_id' => 1],
                2 => ['status' => 'withdrawn', 'pf_transaction_id' => 'PILOT-PAY-003', 'payment_status_id' => 1,
                      'withdrawn_at' => now(), 'refund_status' => 'pending', 'refund_method' => 'bank',
                      'refund_gross' => 150.00, 'refund_net' => 135.00, 'refund_fee' => 15.00],
                3 => ['status' => 'withdrawn', 'pf_transaction_id' => 'PILOT-PAY-004', 'payment_status_id' => 1,
                      'withdrawn_at' => now(), 'refund_status' => 'completed', 'refund_method' => 'wallet',
                      'refund_gross' => 150.00, 'refund_net' => 150.00, 'refund_fee' => 0,
                      'refunded_at' => now()],
                4 => ['status' => 'active',    'pf_transaction_id' => null, 'payment_status_id' => null], // unpaid
                5 => ['status' => 'active',    'pf_transaction_id' => 'PILOT-PAY-DUPE', 'payment_status_id' => 1],
                default => ['status' => 'active'],
            };

            CategoryEventRegistration::create(array_merge([
                'category_event_id' => $catEvent->id,
                'registration_id'   => $registration->id,
                'user_id'           => $user->id,
            ], $state));

            $users[] = $user;
        }

        // Duplicate ITN record — same pf_transaction_id as player[5] to test detection
        CategoryEventRegistration::create([
            'category_event_id' => $catEvent->id,
            'registration_id'   => Registration::create([])->id,
            'user_id'           => $users[0]->id,
            'status'            => 'active',
            'pf_transaction_id' => 'PILOT-PAY-DUPE',  // intentional duplicate
            'payment_status_id' => 1,
        ]);

        PilotEvent::create([
            'event_id'     => $event->id,
            'scenario'     => PilotEvent::SCENARIO_PAYMENT,
            'engine_mode'  => 'legacy',
            'player_count' => $playerCount,
            'draw_count'   => 0,
            'status'       => PilotEvent::STATUS_ACTIVE,
            'notes'        => [
                'paid_count'          => 4,
                'withdrawn_pending'   => 1,
                'withdrawn_refunded'  => 1,
                'unpaid_count'        => 1,
                'duplicate_itn'       => 'PILOT-PAY-DUPE',
            ],
        ]);

        $this->command->info("  [Payment]    event #{$event->id}");
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Create fake players, registrations and active CERs for a category event.
     *
     * @return array<int> Registration IDs
     */
    private function createFakePlayers(CategoryEvent $catEvent, User $admin, int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $user   = User::factory()->create();
            $player = Player::factory()->create(['userId' => $user->id]);

            $registration = Registration::create([]);
            $registration->players()->attach($player->id);

            CategoryEventRegistration::create([
                'category_event_id' => $catEvent->id,
                'registration_id'   => $registration->id,
                'user_id'           => $user->id,
                'status'            => 'active',
            ]);

            $ids[] = $registration->id;
        }
        return $ids;
    }

    /**
     * Create two RR groups of 4 with a simple round-robin fixture grid.
     *
     * @param array<int> $registrationIds
     */
    private function createRRGroups(Draw $draw, array $registrationIds): void
    {
        $chunks = array_chunk($registrationIds, 4);

        foreach ($chunks as $groupIndex => $chunk) {
            $group = DrawGroup::create([
                'draw_id' => $draw->id,
                'name'    => chr(65 + $groupIndex), // A, B
            ]);

            // Assign registrations to group
            foreach ($chunk as $regId) {
                DrawGroupRegistration::create([
                    'draw_group_id'   => $group->id,
                    'registration_id' => $regId,
                ]);
            }

            // Create RR fixtures (round-robin schedule for 4 players = 6 matches)
            $pairs   = [];
            $players = $chunk;
            $n       = count($players);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $pairs[] = [$players[$i], $players[$j]];
                }
            }

            foreach ($pairs as $matchNr => $pair) {
                Fixture::create([
                    'draw_id'          => $draw->id,
                    'stage'            => 'RR',
                    'round'            => 1,
                    'match_nr'         => ($groupIndex * 100) + $matchNr + 1,
                    'registration1_id' => $pair[0],
                    'registration2_id' => $pair[1],
                    'match_status'     => 0,
                    'draw_group_id'    => $group->id,
                ]);
            }
        }
    }

    /**
     * Create a minimal single-elimination bracket (MAIN stage).
     *
     * @param array<int> $registrationIds
     */
    private function createPlayoffFixtures(Draw $draw, array $registrationIds, bool $includeBye = false): void
    {
        // R2 parent
        $parent = Fixture::create([
            'draw_id'      => $draw->id,
            'stage'        => 'MAIN',
            'round'        => 2,
            'match_nr'     => 200,
            'match_status' => 0,
        ]);

        $r1Count = min(4, count($registrationIds));

        for ($i = 0; $i < $r1Count; $i += 2) {
            $r1Id   = $registrationIds[$i]        ?? null;
            $r2Id   = $registrationIds[$i + 1]    ?? null;

            // If includeBye and second slot is empty, leave null (BYE)
            if ($includeBye && $r2Id === null) {
                $r2Id = null;
            }

            Fixture::create([
                'draw_id'            => $draw->id,
                'stage'              => 'MAIN',
                'round'              => 1,
                'match_nr'           => 100 + $i,
                'registration1_id'   => $r1Id,
                'registration2_id'   => $r2Id,
                'match_status'       => 0,
                'parent_fixture_id'  => $parent->id,
            ]);
        }
    }
}
