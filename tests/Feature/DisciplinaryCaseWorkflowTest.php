<?php

namespace Tests\Feature;

use App\Domain\Entries\Services\EntryEligibilityService;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\DisciplinaryCase;
use App\Models\DisciplinarySanction;
use App\Models\Event;
use App\Models\Player;
use App\Models\Registration;
use App\Models\RegistrationOrder;
use App\Models\SiteSetting;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Models\ViolationType;
use App\Services\DisciplinaryCaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DisciplinaryCaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super-user', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        Notification::fake();
    }

    public function test_event_admin_can_report_only_for_their_event_and_report_does_not_convict_player(): void
    {
        [$event, $player] = $this->enteredPlayer();
        $admin = $this->adminFor($event);
        $otherEvent = Event::factory()->create();

        $this->actingAs($admin)->get(route('backend.events.disciplinary.index', $otherEvent))->assertForbidden();
        $this->actingAs($admin)->get(route('backend.events.disciplinary.create', $event))
            ->assertOk()->assertSee('Report incident');

        $type = ViolationType::first();
        $response = $this->actingAs($admin)->post(route('backend.events.disciplinary.store', $event), [
            'player_id' => $player->id,
            'violation_type_id' => $type->id,
            'severity' => 'standard',
            'incident_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'summary' => 'The court official recorded an alleged code violation.',
        ]);

        $case = DisciplinaryCase::firstOrFail();
        $response->assertRedirect(route('backend.disciplinary.cases.show', $case));
        $this->assertSame(DisciplinaryCase::STATUS_SUBMITTED, $case->status);
        $this->assertDatabaseCount('player_violations', 0);
        $this->assertDatabaseCount('disciplinary_sanctions', 0);
        $this->assertDatabaseHas('disciplinary_case_events', ['action' => 'case.reported']);
    }

    public function test_report_rejects_player_who_is_not_entered_in_event(): void
    {
        $event = Event::factory()->create();
        $admin = $this->adminFor($event);
        $player = Player::factory()->create();

        $this->actingAs($admin)->post(route('backend.events.disciplinary.store', $event), [
            'player_id' => $player->id,
            'violation_type_id' => ViolationType::first()->id,
            'severity' => 'standard',
            'incident_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'summary' => 'An allegation with enough factual detail to be reviewed.',
        ])->assertSessionHasErrors('player_id');

        $this->assertDatabaseCount('disciplinary_cases', 0);
    }

    public function test_team_roster_player_is_available_and_can_be_reported_for_team_event(): void
    {
        $event = Event::factory()->create();
        $categoryEvent = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create(['category_event_id' => $categoryEvent->id]);
        $player = Player::factory()->create();
        TeamPlayer::create([
            'team_id' => $team->id,
            'player_id' => $player->id,
            'rank' => 1,
            'pay_status' => 1,
        ]);
        $admin = $this->adminFor($event);

        $this->actingAs($admin)->get(route('backend.events.disciplinary.create', $event))
            ->assertOk()
            ->assertSee($player->name)
            ->assertSee($player->surname);

        $this->actingAs($admin)->post(route('backend.events.disciplinary.store', $event), [
            'player_id' => $player->id,
            'category_event_id' => $categoryEvent->id,
            'violation_type_id' => ViolationType::first()->id,
            'severity' => 'standard',
            'incident_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'summary' => 'The team-event court official recorded an alleged code violation.',
        ])->assertRedirect();

        $this->assertDatabaseHas('disciplinary_cases', [
            'event_id' => $event->id,
            'category_event_id' => $categoryEvent->id,
            'player_id' => $player->id,
        ]);
        $this->assertDatabaseCount('player_violations', 0);
    }

    public function test_only_super_user_can_toggle_disciplinary_case_system(): void
    {
        $super = User::factory()->create()->assignRole('super-user');
        $ordinaryUser = User::factory()->create();

        $this->actingAs($ordinaryUser)->postJson(route('settings.store.single'), [
            'key' => SiteSetting::DISCIPLINARY_SYSTEM_ENABLED,
            'value' => '0',
        ])->assertForbidden();

        $this->actingAs($super)->postJson(route('settings.store.single'), [
            'key' => SiteSetting::DISCIPLINARY_SYSTEM_ENABLED,
            'value' => '0',
        ])->assertOk();

        $this->assertFalse(SiteSetting::disciplinarySystemEnabled());
        $this->actingAs($super)->get(route('backend.superadmin.index'))
            ->assertOk()
            ->assertSee('Disciplinary Case System');
    }

    public function test_disabled_system_is_read_only_and_blocks_http_and_service_mutations(): void
    {
        [$event, $player] = $this->enteredPlayer();
        $admin = $this->adminFor($event);
        $case = app(DisciplinaryCaseService::class)->report($event, $player, $admin, [
            'violation_type_id' => ViolationType::first()->id,
            'incident_at' => now()->subMinute(),
            'summary' => 'An existing allegation remains readable after the workflow is disabled.',
            'severity' => 'standard',
        ]);
        SiteSetting::set(SiteSetting::DISCIPLINARY_SYSTEM_ENABLED, '0', SiteSetting::GROUP_GENERAL);

        $this->actingAs($admin)->get(route('backend.disciplinary.cases.show', $case))
            ->assertOk()
            ->assertSee('read-only')
            ->assertSee($case->case_number);

        $this->actingAs($admin)
            ->from(route('backend.events.disciplinary.index', $event))
            ->post(route('backend.disciplinary.cases.triage', $case), ['action' => 'proceed'])
            ->assertRedirect(route('backend.events.disciplinary.index', $event))
            ->assertSessionHasErrors();

        $this->assertSame(DisciplinaryCase::STATUS_SUBMITTED, $case->fresh()->status);

        $super = User::factory()->create()->assignRole('super-user');
        $this->actingAs($super)
            ->from(route('backend.disciplinary.index'))
            ->post(route('backend.disciplinary.store'), [])
            ->assertRedirect(route('backend.disciplinary.index'))
            ->assertSessionHasErrors();
        $this->assertDatabaseCount('player_violations', 0);

        $this->expectException(\RuntimeException::class);
        app(DisciplinaryCaseService::class)->triage($case, $admin, 'proceed');
    }

    public function test_only_linked_player_account_can_respond(): void
    {
        [$event, $player, $owner] = $this->enteredPlayer();
        $reporter = $this->adminFor($event);
        $case = $this->reportAndTriage($event, $player, $reporter);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->post(route('disciplinary.my-cases.respond', $case), [
            'response' => 'This response must not be accepted by the application.',
        ])->assertForbidden();

        $this->actingAs($owner)->post(route('disciplinary.my-cases.respond', $case), [
            'response' => 'I provide my account of the incident for the independent panel.',
        ])->assertRedirect();

        $this->assertSame(DisciplinaryCase::STATUS_PANEL_REVIEW, $case->fresh()->status);
        $this->assertDatabaseHas('disciplinary_evidence', ['kind' => 'player_response', 'submitted_by' => $owner->id]);
    }

    public function test_panel_requires_three_independent_members_and_reporter_cannot_sit(): void
    {
        [$event, $player] = $this->enteredPlayer();
        $reporter = $this->adminFor($event);
        $case = $this->reportAndTriage($event, $player, $reporter);
        $service = app(DisciplinaryCaseService::class);

        $this->expectException(ValidationException::class);
        $service->appointPanel($case, $reporter, [
            ['user_id' => $reporter->id, 'role' => 'chair'],
            ['user_id' => User::factory()->create()->assignRole('admin')->id, 'role' => 'member'],
            ['user_id' => User::factory()->create()->assignRole('admin')->id, 'role' => 'member'],
        ]);
    }

    public function test_final_decision_is_idempotent_creates_exact_points_and_enforces_scoped_suspension(): void
    {
        [$event, $player, $owner, $categoryEvent] = $this->enteredPlayer();
        $reporter = $this->adminFor($event);
        $case = $this->reportAndTriage($event, $player, $reporter);
        $case->update(['status' => DisciplinaryCase::STATUS_PANEL_REVIEW]);
        [$chair, $member1, $member2] = $this->panelUsers();
        $service = app(DisciplinaryCaseService::class);
        $service->appointPanel($case, $reporter, [
            ['user_id' => $chair->id, 'role' => 'chair'],
            ['user_id' => $member1->id, 'role' => 'member'],
            ['user_id' => $member2->id, 'role' => 'member'],
        ]);

        $decision = $service->finalizeDecision($case, $chair, [
            'outcome' => 'upheld',
            'reasons' => 'The panel unanimously finds the official report reliable and the charge proven.',
            'sanctions' => [[
                'type' => 'suspension', 'scope' => 'event', 'starts_at' => today(),
                'ends_at' => today()->addMonth(), 'details' => 'Suspended from this event.',
            ]],
        ]);

        $expectedPoints = ViolationType::first()->default_points;
        $this->assertSame($expectedPoints, (int) $case->fresh()->charges()->sum('points'));
        $this->assertDatabaseHas('player_violations', [
            'disciplinary_case_id' => $case->id, 'player_id' => $player->id, 'points_assigned' => $expectedPoints,
        ]);
        $this->assertCount(1, $decision->sanctions);

        try {
            app(EntryEligibilityService::class)->assertCanRegister($categoryEvent, $player->id);
            $this->fail('Expected the active event suspension to block entry.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString($case->case_number, $e->getMessage());
        }

        try {
            $service->finalizeDecision($case->fresh(), $chair, [
                'outcome' => 'upheld', 'reasons' => str_repeat('Duplicate decision ', 2), 'sanctions' => [],
            ]);
            $this->fail('Expected duplicate finalization to fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('disciplinary_decisions', 1);
            $this->assertDatabaseCount('disciplinary_sanctions', 1);
            $this->assertDatabaseCount('player_violations', 1);
        }
    }

    public function test_overturned_appeal_revokes_sanction_and_voids_points_without_deleting_history(): void
    {
        [$event, $player, $owner] = $this->enteredPlayer();
        $reporter = $this->adminFor($event);
        $case = $this->reportAndTriage($event, $player, $reporter);
        $case->update(['status' => DisciplinaryCase::STATUS_PANEL_REVIEW]);
        [$chair, $member1, $member2] = $this->panelUsers();
        $service = app(DisciplinaryCaseService::class);
        $service->appointPanel($case, $reporter, [
            ['user_id' => $chair->id, 'role' => 'chair'], ['user_id' => $member1->id, 'role' => 'member'], ['user_id' => $member2->id, 'role' => 'member'],
        ]);
        $service->finalizeDecision($case, $chair, [
            'outcome' => 'upheld', 'reasons' => 'The original panel found the charge proven on the submitted evidence.',
            'sanctions' => [['type' => 'suspension', 'scope' => 'global', 'starts_at' => today(), 'ends_at' => today()->addMonth()]],
        ]);
        $appeal = $service->appeal($case->fresh(), $owner, 'New independent evidence demonstrates that the original identification was mistaken.');
        $appealChair = User::factory()->create()->assignRole('super-user');
        $service->decideAppeal($appeal, $appealChair, 'overturned', 'The new evidence is decisive and the original finding is set aside in full.');

        $this->assertDatabaseCount('player_violations', 1);
        $this->assertNotNull(DB::table('player_violations')->value('voided_at'));
        $this->assertNotNull(DisciplinarySanction::first()->revoked_at);
        $this->assertSame(DisciplinaryCase::STATUS_FINAL, $case->fresh()->status);
    }

    public function test_existing_checkout_is_blocked_before_payment_when_sanction_becomes_active(): void
    {
        [$event, $player, $owner, $categoryEvent] = $this->enteredPlayer();
        $reporter = $this->adminFor($event);
        $case = $this->reportAndTriage($event, $player, $reporter);
        $case->update(['status' => DisciplinaryCase::STATUS_PANEL_REVIEW]);
        [$chair, $member1, $member2] = $this->panelUsers();
        $service = app(DisciplinaryCaseService::class);
        $service->appointPanel($case, $reporter, [
            ['user_id' => $chair->id, 'role' => 'chair'], ['user_id' => $member1->id, 'role' => 'member'], ['user_id' => $member2->id, 'role' => 'member'],
        ]);
        $order = RegistrationOrder::create(['user_id' => $owner->id, 'pay_status' => 0]);
        DB::table('registration_order_items')->insert([
            'order_id' => $order->id, 'category_event_id' => $categoryEvent->id,
            'registration_id' => Registration::factory()->create()->id, 'player_id' => $player->id,
            'item_price' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $service->finalizeDecision($case, $chair, [
            'outcome' => 'upheld', 'reasons' => 'The panel finds the charge proven and imposes a temporary global suspension.',
            'sanctions' => [['type' => 'suspension', 'scope' => 'global', 'starts_at' => today(), 'ends_at' => today()->addMonth()]],
        ]);

        $this->withoutMiddleware([
            \App\Http\Middleware\EnsureAgreementAccepted::class,
            \App\Http\Middleware\EnsurePlayerProfileUpdated::class,
        ]);
        $this->actingAs($owner)->get(route('registration.checkout', $order))
            ->assertRedirect()->assertSessionHasErrors();
        $this->assertSame(0, (int) $order->fresh()->pay_status);
    }

    private function enteredPlayer(): array
    {
        $event = Event::factory()->create();
        $categoryEvent = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $player = Player::factory()->create();
        $owner = User::factory()->create();
        $owner->players()->attach($player->id);
        $registration = Registration::factory()->create();
        $registration->players()->attach($player->id);
        CategoryEventRegistration::factory()->paid()->create([
            'category_event_id' => $categoryEvent->id,
            'registration_id' => $registration->id,
            'user_id' => $owner->id,
        ]);
        return [$event, $player, $owner, $categoryEvent];
    }

    private function adminFor(Event $event): User
    {
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        return $admin;
    }

    private function reportAndTriage(Event $event, Player $player, User $reporter): DisciplinaryCase
    {
        $service = app(DisciplinaryCaseService::class);
        $case = $service->report($event, $player, $reporter, [
            'violation_type_id' => ViolationType::first()->id,
            'incident_at' => now()->subMinute(),
            'summary' => 'A sufficiently detailed alleged on-court code violation for panel review.',
            'severity' => 'standard',
        ]);
        return $service->triage($case, $reporter, 'proceed');
    }

    private function panelUsers(): array
    {
        return [
            User::factory()->create()->assignRole('admin'),
            User::factory()->create()->assignRole('admin'),
            User::factory()->create()->assignRole('admin'),
        ];
    }
}
