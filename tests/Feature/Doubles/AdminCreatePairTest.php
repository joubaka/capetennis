<?php

declare(strict_types=1);

namespace Tests\Feature\Doubles;

use App\Domain\Doubles\Services\PairService;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Draw;
use App\Models\Player;
use App\Models\Registration;
use App\Models\RegistrationPair;
use App\Models\User;
use App\Services\FeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AdminCreatePairTest
 *
 * DOUBLES PHASE 2 — verifies:
 *   - pair creation via PairService and HTTP endpoint
 *   - duplicate pair prevention
 *   - same-player prevention
 *   - player-already-paired prevention
 *   - draw eligibility (pair appears in category registrations)
 *   - pair deletion (when not locked / not in published draw)
 *   - deletion blocked when category is locked
 */
class AdminCreatePairTest extends TestCase
{
    use RefreshDatabase;

    private PairService $pairService;
    private CategoryEvent $categoryEvent;
    private Player $p1;
    private Player $p2;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable feature flag
        config(['feature_flags.doubles_foundation' => true]);

        $this->pairService   = app(PairService::class);
        $this->categoryEvent = CategoryEvent::factory()->create(['is_doubles' => true]);
        $this->p1            = Player::factory()->create(['name' => 'Anna', 'surname' => 'Adams']);
        $this->p2            = Player::factory()->create(['name' => 'Bella', 'surname' => 'Brown']);
        $this->admin         = User::factory()->create();
    }

    // -----------------------------------------------------------------------
    // 1. PAIR CREATION
    // -----------------------------------------------------------------------

    /** @test */
    public function pair_can_be_created_via_service(): void
    {
        $pair = $this->pairService->createPair(
            $this->categoryEvent,
            $this->p1->id,
            $this->p2->id,
            $this->admin
        );

        $this->assertInstanceOf(RegistrationPair::class, $pair);
        $this->assertEquals(RegistrationPair::STATUS_ACTIVE, $pair->status);
        $this->assertEquals($this->categoryEvent->id, $pair->category_event_id);
    }

    /** @test */
    public function pair_creation_creates_registration_with_two_players(): void
    {
        $pair = $this->pairService->createPair(
            $this->categoryEvent,
            $this->p1->id,
            $this->p2->id,
            $this->admin
        );

        $reg = Registration::with('players')->find($pair->registration_id);
        $this->assertNotNull($reg);
        $this->assertCount(2, $reg->players);

        $playerIds = $reg->players->pluck('id')->sort()->values();
        $this->assertEquals(collect([$this->p1->id, $this->p2->id])->sort()->values(), $playerIds);
    }

    /** @test */
    public function pair_creation_creates_paid_category_event_registration(): void
    {
        $pair = $this->pairService->createPair(
            $this->categoryEvent,
            $this->p1->id,
            $this->p2->id,
            $this->admin
        );

        $cer = CategoryEventRegistration::where('registration_id', $pair->registration_id)
            ->where('category_event_id', $this->categoryEvent->id)
            ->first();

        $this->assertNotNull($cer);
        $this->assertEquals(1, $cer->payment_status_id);
        $this->assertEquals('active', $cer->status);
    }

    /** @test */
    public function pair_display_name_shows_both_players(): void
    {
        $pair = $this->pairService->createPair(
            $this->categoryEvent,
            $this->p1->id,
            $this->p2->id,
            $this->admin
        );

        $reg = Registration::with('players')->find($pair->registration_id);
        // Adams before Brown alphabetically
        $this->assertEquals('Anna Adams / Bella Brown', $reg->displayName());
        $this->assertEquals('Adams / Brown', $reg->displayShortName());
    }

    // -----------------------------------------------------------------------
    // 2. DUPLICATE PREVENTION
    // -----------------------------------------------------------------------

    /** @test */
    public function duplicate_pair_is_rejected(): void
    {
        $this->pairService->createPair(
            $this->categoryEvent,
            $this->p1->id,
            $this->p2->id,
            $this->admin
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already registered');

        $this->pairService->createPair(
            $this->categoryEvent,
            $this->p1->id,
            $this->p2->id,
            $this->admin
        );
    }

    /** @test */
    public function reversed_duplicate_pair_is_also_rejected(): void
    {
        $this->pairService->createPair(
            $this->categoryEvent,
            $this->p1->id,
            $this->p2->id,
            $this->admin
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already registered');

        // Same pair, reversed order
        $this->pairService->createPair(
            $this->categoryEvent,
            $this->p2->id,
            $this->p1->id,
            $this->admin
        );
    }

    // -----------------------------------------------------------------------
    // 3. SAME-PLAYER PREVENTION
    // -----------------------------------------------------------------------

    /** @test */
    public function same_player_twice_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot be paired with themselves');

        $this->pairService->createPair(
            $this->categoryEvent,
            $this->p1->id,
            $this->p1->id,
            $this->admin
        );
    }

    // -----------------------------------------------------------------------
    // 4. PLAYER ALREADY PAIRED IN CATEGORY
    // -----------------------------------------------------------------------

    /** @test */
    public function player_already_in_active_pair_cannot_join_another(): void
    {
        $p3 = Player::factory()->create(['name' => 'Carol', 'surname' => 'Cole']);

        // p1 pairs with p2
        $this->pairService->createPair(
            $this->categoryEvent,
            $this->p1->id,
            $this->p2->id,
            $this->admin
        );

        // Try to pair p1 with p3 — should fail
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already paired');

        $this->pairService->createPair(
            $this->categoryEvent,
            $this->p1->id,
            $p3->id,
            $this->admin
        );
    }

    // -----------------------------------------------------------------------
    // 5. DRAW ELIGIBILITY
    // -----------------------------------------------------------------------

    /** @test */
    public function pair_registration_appears_in_category_event_registrations(): void
    {
        $pair = $this->pairService->createPair(
            $this->categoryEvent,
            $this->p1->id,
            $this->p2->id,
            $this->admin
        );

        $registrationIds = $this->categoryEvent
            ->categoryEventRegistrations()
            ->where('payment_status_id', 1)
            ->where('status', 'active')
            ->pluck('registration_id');

        $this->assertContains($pair->registration_id, $registrationIds);
    }

    /** @test */
    public function multiple_pairs_appear_in_draw_eligible_list(): void
    {
        $p3 = Player::factory()->create(['name' => 'Carol', 'surname' => 'Cole']);
        $p4 = Player::factory()->create(['name' => 'Diana', 'surname' => 'Davis']);

        $pair1 = $this->pairService->createPair($this->categoryEvent, $this->p1->id, $this->p2->id, $this->admin);
        $pair2 = $this->pairService->createPair($this->categoryEvent, $p3->id, $p4->id, $this->admin);

        $registrationIds = $this->categoryEvent
            ->categoryEventRegistrations()
            ->where('payment_status_id', 1)
            ->where('status', 'active')
            ->pluck('registration_id');

        $this->assertContains($pair1->registration_id, $registrationIds);
        $this->assertContains($pair2->registration_id, $registrationIds);
    }

    // -----------------------------------------------------------------------
    // 6. PAIR DELETION
    // -----------------------------------------------------------------------

    /** @test */
    public function pair_can_be_removed_when_not_locked(): void
    {
        $pair = $this->pairService->createPair(
            $this->categoryEvent,
            $this->p1->id,
            $this->p2->id,
            $this->admin
        );

        $this->pairService->removePair($pair, $this->admin);

        $pair->refresh();
        $this->assertEquals(RegistrationPair::STATUS_DISSOLVED, $pair->status);

        // CER should be withdrawn
        $cer = CategoryEventRegistration::where('registration_id', $pair->registration_id)
            ->where('category_event_id', $this->categoryEvent->id)
            ->first();
        $this->assertEquals('withdrawn', $cer->status);
    }

    /** @test */
    public function pair_cannot_be_removed_when_category_is_locked(): void
    {
        $pair = $this->pairService->createPair(
            $this->categoryEvent,
            $this->p1->id,
            $this->p2->id,
            $this->admin
        );

        // Lock the category
        $this->categoryEvent->update(['locked_at' => now()]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('locked');

        $this->pairService->removePair($pair, $this->admin);
    }

    /** @test */
    public function pair_cannot_be_removed_when_draw_is_published(): void
    {
        $pair = $this->pairService->createPair(
            $this->categoryEvent,
            $this->p1->id,
            $this->p2->id,
            $this->admin
        );

        // Simulate a published draw
        Draw::factory()->create([
            'category_event_id' => $this->categoryEvent->id,
            'published'         => 1,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('published');

        $this->pairService->removePair($pair, $this->admin);
    }

    // -----------------------------------------------------------------------
    // 7. HTTP ENDPOINT — STORE
    // -----------------------------------------------------------------------

    /** @test */
    public function http_store_creates_pair_and_returns_json(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson(
            route('admin.doubles.pairs.store', $this->categoryEvent),
            ['player1_id' => $this->p1->id, 'player2_id' => $this->p2->id]
        );

        $response->assertStatus(201)
                 ->assertJson(['success' => true]);

        $this->assertDatabaseHas('registration_pairs', [
            'category_event_id' => $this->categoryEvent->id,
            'status'            => RegistrationPair::STATUS_ACTIVE,
        ]);
    }

    /** @test */
    public function http_store_returns_422_for_duplicate_pair(): void
    {
        $this->actingAs($this->admin);

        $this->pairService->createPair($this->categoryEvent, $this->p1->id, $this->p2->id, $this->admin);

        $response = $this->postJson(
            route('admin.doubles.pairs.store', $this->categoryEvent),
            ['player1_id' => $this->p1->id, 'player2_id' => $this->p2->id]
        );

        $response->assertStatus(422)
                 ->assertJson(['success' => false]);
    }

    /** @test */
    public function http_destroy_removes_pair(): void
    {
        $this->actingAs($this->admin);

        $pair = $this->pairService->createPair($this->categoryEvent, $this->p1->id, $this->p2->id, $this->admin);

        $response = $this->deleteJson(
            route('admin.doubles.pairs.destroy', [$this->categoryEvent, $pair])
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertEquals(RegistrationPair::STATUS_DISSOLVED, $pair->fresh()->status);
    }

    // -----------------------------------------------------------------------
    // 8. ELIGIBLE PLAYERS ENDPOINT
    // -----------------------------------------------------------------------

    /** @test */
    public function eligible_players_endpoint_excludes_already_paired_players(): void
    {
        $this->actingAs($this->admin);

        // Create p1+p2 as a pair
        $this->pairService->createPair($this->categoryEvent, $this->p1->id, $this->p2->id, $this->admin);

        $response = $this->getJson(
            route('admin.doubles.pairs.eligiblePlayers', $this->categoryEvent)
        );

        $response->assertOk();
        $playerIds = collect($response->json('players'))->pluck('id');

        $this->assertNotContains($this->p1->id, $playerIds);
        $this->assertNotContains($this->p2->id, $playerIds);
    }
}
