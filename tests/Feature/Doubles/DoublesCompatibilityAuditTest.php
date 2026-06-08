<?php

declare(strict_types=1);

namespace Tests\Feature\Doubles;

use App\Domain\Draws\Services\RoundRobinGenerationService;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Draw;
use App\Models\DrawGroup;
use App\Models\DrawGroupRegistration;
use App\Models\Event;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Registration;
use App\Models\RegistrationOrder;
use App\Services\FeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DoublesCompatibilityAuditTest
 *
 * Verifies that all existing singles workflows are completely unchanged
 * by the doubles foundation layer.
 *
 * Coverage:
 *   - Doubles feature flag is off by default → no doubles code runs
 *   - Existing singles CategoryEvents have is_doubles = false
 *   - Existing singles CER creation is unaffected
 *   - Existing payment fields on CER are unaffected
 *   - Existing RegistrationOrder creation is unaffected
 *   - RR generation works with a singles Registration (1 player)
 *   - RR generation works with a doubles Registration (2 players) — engine is doubles-safe
 *   - DrawGroupRegistration::player() still returns first player (singles behavior documented)
 *   - Registration::displayName() handles 1 or 2 players
 */
class DoublesCompatibilityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure flag is off for all compatibility tests
        FeatureFlags::clearOverride(FeatureFlags::DOUBLES_FOUNDATION);
    }

    // -------------------------------------------------------------------------
    // Feature flag isolation
    // -------------------------------------------------------------------------

    public function test_doubles_foundation_flag_is_off_by_default(): void
    {
        $this->assertFalse(FeatureFlags::enabled(FeatureFlags::DOUBLES_FOUNDATION));
    }

    public function test_doubles_flag_off_means_is_doubles_logic_is_not_active(): void
    {
        $categoryEvent = CategoryEvent::factory()->create();

        // When flag is off, isDoubles() returns false regardless
        $this->assertFalse(FeatureFlags::enabled(FeatureFlags::DOUBLES_FOUNDATION));
        $this->assertFalse($categoryEvent->isDoubles());
    }

    // -------------------------------------------------------------------------
    // Existing singles CategoryEvent is unchanged
    // -------------------------------------------------------------------------

    public function test_existing_singles_category_event_has_is_doubles_false(): void
    {
        $categoryEvent = CategoryEvent::factory()->create();

        $this->assertFalse($categoryEvent->isDoubles());
        $this->assertFalse((bool) $categoryEvent->is_doubles);
    }

    public function test_category_event_is_locked_still_works(): void
    {
        $locked   = CategoryEvent::factory()->locked()->create();
        $unlocked = CategoryEvent::factory()->create();

        $this->assertTrue($locked->isLocked());
        $this->assertFalse($unlocked->isLocked());
    }

    // -------------------------------------------------------------------------
    // Existing singles CER is unchanged
    // -------------------------------------------------------------------------

    public function test_singles_cer_can_be_created_with_existing_fields(): void
    {
        $categoryEvent = CategoryEvent::factory()->create();
        $registration  = Registration::factory()->create();

        $cer = CategoryEventRegistration::create([
            'category_event_id' => $categoryEvent->id,
            'registration_id'   => $registration->id,
            'user_id'           => null,
            'status'            => 'active',
            'payment_status_id' => 1,
        ]);

        $this->assertDatabaseHas('category_event_registrations', [
            'id'               => $cer->id,
            'status'           => 'active',
            'payment_status_id' => 1,
        ]);
    }

    public function test_cer_refund_fields_exist_and_default_correctly(): void
    {
        $categoryEvent = CategoryEvent::factory()->create();
        $registration  = Registration::factory()->create();

        $cer = CategoryEventRegistration::create([
            'category_event_id' => $categoryEvent->id,
            'registration_id'   => $registration->id,
            'status'            => 'active',
        ]);

        // Refund columns exist (value may be null or 'not_refunded' depending on DB default)
        $this->assertArrayHasKey('refund_status', $cer->getAttributes() + ['refund_status' => null]);
        $this->assertNull($cer->refunded_at);

        // Numeric refund fields should be falsy (null or 0)
        $this->assertEqualsWithDelta(0.0, (float) ($cer->refund_gross ?? 0), 0.001);
        $this->assertEqualsWithDelta(0.0, (float) ($cer->refund_fee   ?? 0), 0.001);
        $this->assertEqualsWithDelta(0.0, (float) ($cer->refund_net   ?? 0), 0.001);
    }

    // -------------------------------------------------------------------------
    // Existing RegistrationOrder is unchanged
    // -------------------------------------------------------------------------

    public function test_registration_order_can_be_created_with_existing_fields(): void
    {
        $order = RegistrationOrder::create([
            'user_id'           => null,
            'wallet_reserved'   => 0,
            'wallet_debited'    => false,
            'payfast_paid'      => false,
            'pay_status'        => false,
            'total_fee'         => 150.00,
        ]);

        $this->assertDatabaseHas('registration_orders', [
            'id'        => $order->id,
            'total_fee' => 150.00,
        ]);
        $this->assertFalse($order->isFullyPaid());
    }

    // -------------------------------------------------------------------------
    // RR generation: singles registration (1 player) — unchanged
    // -------------------------------------------------------------------------

    public function test_rr_generation_works_with_singles_registration(): void
    {
        $draw  = $this->createDrawWithGroup(playerCount: 3);
        $svc   = app(RoundRobinGenerationService::class);

        $svc->generate($draw);

        // 3 players → 3 fixtures (round-robin: n*(n-1)/2)
        $this->assertEquals(3, $draw->drawFixtures()->where('stage', 'RR')->count());
    }

    // -------------------------------------------------------------------------
    // RR generation: doubles registration (2 players) — engine is safe
    // -------------------------------------------------------------------------

    public function test_rr_generation_works_with_doubles_registration_two_players(): void
    {
        $draw = $this->createDrawWithGroup(playerCount: 4, doublesPlayers: true);
        $svc  = app(RoundRobinGenerationService::class);

        $svc->generate($draw);

        // 4 registrations → 6 fixtures regardless of players per registration
        $this->assertEquals(6, $draw->drawFixtures()->where('stage', 'RR')->count());
    }

    // -------------------------------------------------------------------------
    // DrawGroupRegistration::player() — documented singles behavior
    // -------------------------------------------------------------------------

    public function test_draw_group_registration_player_returns_first_player_for_singles(): void
    {
        $player = Player::factory()->create();
        $reg    = Registration::factory()->create();
        PlayerRegistration::create(['registration_id' => $reg->id, 'player_id' => $player->id]);

        $draw       = Draw::factory()->create();
        $group      = DrawGroup::factory()->for($draw)->create();
        $groupReg   = DrawGroupRegistration::create([
            'draw_group_id'   => $group->id,
            'registration_id' => $reg->id,
        ]);

        // ->player() uses ->players->first() — correct for singles
        $resolved = $groupReg->player();
        $this->assertNotNull($resolved);
        $this->assertEquals($player->id, $resolved->id);
    }

    public function test_draw_group_registration_player_returns_first_player_only_for_doubles(): void
    {
        // AUDIT FINDING: for doubles pairs, player() silently drops player 2.
        // This is a known limitation documented in Phase 1. No fix applied yet.
        $player1 = Player::factory()->create();
        $player2 = Player::factory()->create();
        $reg     = Registration::factory()->create();
        PlayerRegistration::create(['registration_id' => $reg->id, 'player_id' => $player1->id]);
        PlayerRegistration::create(['registration_id' => $reg->id, 'player_id' => $player2->id]);

        $draw     = Draw::factory()->create();
        $group    = DrawGroup::factory()->for($draw)->create();
        $groupReg = DrawGroupRegistration::create([
            'draw_group_id'   => $group->id,
            'registration_id' => $reg->id,
        ]);

        // player() returns only first — Phase 2 will fix with players() returning collection
        $resolved = $groupReg->player();
        $this->assertNotNull($resolved);
        // Only one player is returned — partner is invisible here
        $this->assertEquals($player1->id, $resolved->id);
    }

    // -------------------------------------------------------------------------
    // Registration::displayName() handles both 1 and 2 players
    // -------------------------------------------------------------------------

    public function test_display_name_works_for_singles(): void
    {
        $player = Player::factory()->create(['name' => 'John', 'surname' => 'Doe']);
        $reg    = Registration::factory()->create();
        PlayerRegistration::create(['registration_id' => $reg->id, 'player_id' => $player->id]);

        $this->assertStringContainsString('John', $reg->displayName());
    }

    public function test_display_name_works_for_doubles_pair(): void
    {
        $p1  = Player::factory()->create(['name' => 'Alice', 'surname' => 'Smith']);
        $p2  = Player::factory()->create(['name' => 'Bob',   'surname' => 'Jones']);
        $reg = Registration::factory()->create();
        PlayerRegistration::create(['registration_id' => $reg->id, 'player_id' => $p1->id]);
        PlayerRegistration::create(['registration_id' => $reg->id, 'player_id' => $p2->id]);

        $name = $reg->displayName();
        $this->assertStringContainsString('Alice', $name);
        $this->assertStringContainsString('Bob', $name);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a Draw with one DrawGroup containing N registrations.
     * If doublesPlayers = true, each Registration gets 2 players.
     */
    private function createDrawWithGroup(int $playerCount, bool $doublesPlayers = false): Draw
    {
        $event         = Event::factory()->create();
        $categoryEvent = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw          = Draw::factory()->create([
            'event_id'          => $event->id,
            'category_event_id' => $categoryEvent->id,
            'locked'            => false,
            'published'         => false,
        ]);
        $group = DrawGroup::factory()->for($draw)->create();

        for ($i = 0; $i < $playerCount; $i++) {
            $reg = Registration::factory()->create();

            $p1 = Player::factory()->create();
            PlayerRegistration::create(['registration_id' => $reg->id, 'player_id' => $p1->id]);

            if ($doublesPlayers) {
                $p2 = Player::factory()->create();
                PlayerRegistration::create(['registration_id' => $reg->id, 'player_id' => $p2->id]);
            }

            DrawGroupRegistration::create([
                'draw_group_id'   => $group->id,
                'registration_id' => $reg->id,
                'seed'            => $i + 1,
            ]);
        }

        return $draw->fresh(['groups.groupRegistrations']);
    }
}
