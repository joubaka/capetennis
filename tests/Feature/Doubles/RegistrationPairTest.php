<?php

declare(strict_types=1);

namespace Tests\Feature\Doubles;

use App\Models\CategoryEventRegistration;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Registration;
use App\Models\RegistrationPair;
use App\Models\CategoryEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RegistrationPairTest
 *
 * Verifies the RegistrationPair foundation model:
 *   - table creation
 *   - fillable fields
 *   - status/payment constants
 *   - all relationships resolve without error
 *   - a Registration can hold two players (the core doubles assumption)
 */
class RegistrationPairTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Table + Model basics
    // -------------------------------------------------------------------------

    public function test_registration_pairs_table_exists(): void
    {
        $this->assertTrue(\Schema::hasTable('registration_pairs'));
    }

    public function test_registration_pair_can_be_created(): void
    {
        $registration = Registration::factory()->create();
        $categoryEvent = CategoryEvent::factory()->create();

        $pair = RegistrationPair::create([
            'registration_id'  => $registration->id,
            'category_event_id' => $categoryEvent->id,
            'status'           => RegistrationPair::STATUS_PENDING_PARTNER,
        ]);

        $this->assertDatabaseHas('registration_pairs', [
            'id'               => $pair->id,
            'registration_id'  => $registration->id,
            'category_event_id' => $categoryEvent->id,
            'status'           => 'pending_partner',
        ]);
    }

    public function test_all_status_constants_are_defined(): void
    {
        $this->assertSame('pending_partner', RegistrationPair::STATUS_PENDING_PARTNER);
        $this->assertSame('invited',         RegistrationPair::STATUS_INVITED);
        $this->assertSame('active',          RegistrationPair::STATUS_ACTIVE);
        $this->assertSame('incomplete',      RegistrationPair::STATUS_INCOMPLETE);
        $this->assertSame('dissolved',       RegistrationPair::STATUS_DISSOLVED);
    }

    public function test_payment_model_constants_are_defined(): void
    {
        $this->assertSame('full',  RegistrationPair::PAYMENT_FULL);
        $this->assertSame('split', RegistrationPair::PAYMENT_SPLIT);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function test_registration_relationship_resolves(): void
    {
        $registration  = Registration::factory()->create();
        $categoryEvent = CategoryEvent::factory()->create();

        $pair = RegistrationPair::create([
            'registration_id'   => $registration->id,
            'category_event_id' => $categoryEvent->id,
            'status'            => RegistrationPair::STATUS_PENDING_PARTNER,
        ]);

        $this->assertInstanceOf(Registration::class, $pair->registration);
        $this->assertEquals($registration->id, $pair->registration->id);
    }

    public function test_category_event_relationship_resolves(): void
    {
        $registration  = Registration::factory()->create();
        $categoryEvent = CategoryEvent::factory()->create();

        $pair = RegistrationPair::create([
            'registration_id'   => $registration->id,
            'category_event_id' => $categoryEvent->id,
            'status'            => RegistrationPair::STATUS_PENDING_PARTNER,
        ]);

        $this->assertInstanceOf(CategoryEvent::class, $pair->categoryEvent);
        $this->assertEquals($categoryEvent->id, $pair->categoryEvent->id);
    }

    public function test_player_cer_relationships_are_nullable(): void
    {
        $registration  = Registration::factory()->create();
        $categoryEvent = CategoryEvent::factory()->create();

        $pair = RegistrationPair::create([
            'registration_id'   => $registration->id,
            'category_event_id' => $categoryEvent->id,
            'status'            => RegistrationPair::STATUS_PENDING_PARTNER,
        ]);

        $this->assertNull($pair->player1_cer_id);
        $this->assertNull($pair->player2_cer_id);
        $this->assertNull($pair->player1Cer);
        $this->assertNull($pair->player2Cer);
    }

    public function test_invite_fields_are_nullable(): void
    {
        $registration  = Registration::factory()->create();
        $categoryEvent = CategoryEvent::factory()->create();

        $pair = RegistrationPair::create([
            'registration_id'   => $registration->id,
            'category_event_id' => $categoryEvent->id,
            'status'            => RegistrationPair::STATUS_PENDING_PARTNER,
        ]);

        $this->assertNull($pair->invite_token);
        $this->assertNull($pair->invite_email);
        $this->assertNull($pair->invite_expires_at);
        $this->assertNull($pair->accepted_at);
        $this->assertNull($pair->payment_model);
    }

    // -------------------------------------------------------------------------
    // Core doubles assumption: one Registration can hold two players
    // -------------------------------------------------------------------------

    public function test_one_registration_can_hold_two_players(): void
    {
        $player1 = Player::factory()->create();
        $player2 = Player::factory()->create();

        $registration = Registration::factory()->create();

        PlayerRegistration::create(['registration_id' => $registration->id, 'player_id' => $player1->id]);
        PlayerRegistration::create(['registration_id' => $registration->id, 'player_id' => $player2->id]);

        $registration->refresh();

        $this->assertCount(2, $registration->players);
        $this->assertTrue($registration->players->contains('id', $player1->id));
        $this->assertTrue($registration->players->contains('id', $player2->id));
    }

    public function test_registration_display_name_includes_both_players(): void
    {
        $player1 = Player::factory()->create(['name' => 'Alice', 'surname' => 'Smith']);
        $player2 = Player::factory()->create(['name' => 'Bob',   'surname' => 'Jones']);

        $registration = Registration::factory()->create();

        PlayerRegistration::create(['registration_id' => $registration->id, 'player_id' => $player1->id]);
        PlayerRegistration::create(['registration_id' => $registration->id, 'player_id' => $player2->id]);

        $displayName = $registration->displayName();

        $this->assertStringContainsString('Alice', $displayName);
        $this->assertStringContainsString('Bob', $displayName);
    }

    public function test_registration_pair_stores_registration_id(): void
    {
        $registration  = Registration::factory()->create();
        $categoryEvent = CategoryEvent::factory()->create();

        $pair = RegistrationPair::create([
            'registration_id'   => $registration->id,
            'category_event_id' => $categoryEvent->id,
            'status'            => RegistrationPair::STATUS_PENDING_PARTNER,
        ]);

        $this->assertDatabaseHas('registration_pairs', [
            'id'              => $pair->id,
            'registration_id' => $registration->id,
        ]);
    }
}
