<?php

namespace Tests\Feature;

use App\Domain\Entries\Services\EntryEligibilityService;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\DisciplinaryCase;
use App\Models\DisciplinarySanction;
use App\Models\PlayerSuspension;
use App\Models\PlayerViolation;
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
use App\Services\PlayerEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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
        $this->actingAs($super)->get(route('backend.superadmin.workspace'))
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

    public function test_event_disqualification_blocks_only_the_target_event_and_respects_revocation(): void
    {
        [$case, $owner, $chair] = $this->decidedCase('event_disqualification', 'event');
        $eligibility = app(PlayerEligibilityService::class);
        $eligibility->assertEligible($case->player_id, Event::factory()->create());
        $this->assertCount(1, $eligibility->activeRestrictions($case->player_id, $case->event));
        try {
            $eligibility->assertEligible($case->player_id, $case->event);
            $this->fail('Disqualification must block entry.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString($case->case_number, $e->getMessage());
        }
        $case->sanctions()->update(['revoked_at' => now(), 'revoked_by' => $chair->id]);
        $eligibility->assertEligible($case->player_id, $case->event);
    }

    public function test_legacy_suspension_blocks_entry_but_future_expired_and_lifted_records_do_not(): void
    {
        [$event, $player] = $this->enteredPlayer();
        $suspension = PlayerSuspension::create([
            'player_id' => $player->id, 'triggered_at' => today(), 'suspension_number' => 1,
            'duration_months' => 1, 'starts_at' => today(), 'ends_at' => today()->addMonth(),
        ]);
        $eligibility = app(PlayerEligibilityService::class);
        try {
            $eligibility->assertEligible($player, $event);
            $this->fail('Legacy suspension must block entry.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('active suspension', $e->getMessage());
        }
        $suspension->update(['starts_at' => today()->addDay()]);
        $eligibility->assertEligible($player, $event);
        $suspension->update(['starts_at' => today()->subMonth(), 'ends_at' => today()]);
        $eligibility->assertEligible($player, $event);
        $suspension->update(['ends_at' => today()->addMonth(), 'lifted_at' => now()]);
        $eligibility->assertEligible($player, $event);
    }

    public function test_linked_accused_account_cannot_be_appointed_even_as_super_user(): void
    {
        [$event, $player, $owner] = $this->enteredPlayer();
        $owner->assignRole('super-user');
        $reporter = $this->adminFor($event);
        $case = $this->reportAndTriage($event, $player, $reporter);
        [, $member1, $member2] = $this->panelUsers();
        $this->actingAs($reporter)->post(route('backend.disciplinary.cases.panel', $case), [
            'members' => $this->members($owner, $member1, $member2),
        ])->assertSessionHasErrors('panel');
        $this->assertDatabaseCount('disciplinary_case_assignments', 0);
    }

    public function test_new_player_link_after_appointment_blocks_final_decision(): void
    {
        [$event, $player, $owner] = $this->enteredPlayer();
        $reporter = $this->adminFor($event);
        $case = $this->reportAndTriage($event, $player, $reporter);
        app(DisciplinaryCaseService::class)->submitResponse($case, $owner, 'My response for the panel.');
        [$chair, $member1, $member2] = $this->panelUsers();
        app(DisciplinaryCaseService::class)->appointPanel($case->fresh(), $reporter, $this->members($chair, $member1, $member2));
        $member1->players()->attach($player->id);
        $this->actingAs($chair)->post(route('backend.disciplinary.cases.decision', $case), [
            'outcome' => 'upheld', 'reasons' => 'A proposed decision with enough explanatory reasons.',
        ])->assertSessionHasErrors('panel');
        $this->assertDatabaseCount('disciplinary_decisions', 0);
        $this->assertDatabaseCount('disciplinary_sanctions', 0);
    }

    public function test_recusal_cannot_be_self_reversed_and_removes_case_list_access(): void
    {
        [$event, $player] = $this->enteredPlayer();
        $reporter = $this->adminFor($event);
        $case = $this->reportAndTriage($event, $player, $reporter);
        [$chair, $member1, $member2] = $this->panelUsers();
        app(DisciplinaryCaseService::class)->appointPanel($case, $reporter, $this->members($chair, $member1, $member2));
        $assignment = $case->assignments()->where('user_id', $chair->id)->firstOrFail();
        $this->actingAs($chair)->get(route('backend.disciplinary.cases.index'))->assertSee($case->case_number);
        $this->post(route('backend.disciplinary.cases.panel.conflict', [$case, $assignment]), [
            'conflict' => true, 'notes' => 'I have a conflict of interest.',
        ])->assertSessionHasNoErrors();
        $this->post(route('backend.disciplinary.cases.panel.conflict', [$case, $assignment]), ['conflict' => false])
            ->assertSessionHasErrors('panel');
        $this->assertNotNull($assignment->fresh()->recused_at);
        $this->assertSame('I have a conflict of interest.', $assignment->fresh()->conflict_notes);
        $this->get(route('backend.disciplinary.cases.index'))->assertDontSee($case->case_number);
        $this->get(route('backend.disciplinary.cases.show', $case))->assertForbidden();
    }

    public function test_replacement_panel_preserves_assignments_and_cannot_restore_recused_member(): void
    {
        [$event, $player] = $this->enteredPlayer();
        $reporter = $this->adminFor($event);
        $case = $this->reportAndTriage($event, $player, $reporter);
        [$chair, $member1, $member2] = $this->panelUsers();
        $service = app(DisciplinaryCaseService::class);
        $service->appointPanel($case, $reporter, $this->members($chair, $member1, $member2));
        $old = $case->assignments()->where('user_id', $member2->id)->firstOrFail();
        $service->declareConflict($old, $member2, true, 'Original conflict details.');
        $replacement = User::factory()->create()->assignRole('admin');
        $service->appointPanel($case, $reporter, $this->members($chair, $member1, $replacement));
        $this->assertDatabaseCount('disciplinary_case_assignments', 4);
        $this->assertSame('Original conflict details.', $old->fresh()->conflict_notes);
        $this->actingAs($reporter)->post(route('backend.disciplinary.cases.panel', $case), [
            'members' => $this->members($chair, $member1, $member2),
        ])->assertSessionHasErrors('panel');
        $this->assertDatabaseCount('disciplinary_case_assignments', 4);
    }

    public function test_panel_cannot_be_replaced_or_recused_after_finalization(): void
    {
        [$case, , $chair] = $this->decidedCase();
        $reporter = $case->reporter;
        [$newChair, $member1, $member2] = $this->panelUsers();
        $this->actingAs($reporter)->post(route('backend.disciplinary.cases.panel', $case), [
            'members' => $this->members($newChair, $member1, $member2),
        ])->assertSessionHasErrors('panel');
        $assignment = $case->assignments()->where('user_id', $chair->id)->firstOrFail();
        $this->actingAs($chair)->post(route('backend.disciplinary.cases.panel.conflict', [$case, $assignment]), ['conflict' => true])
            ->assertSessionHasErrors('panel');
        $this->assertNull($assignment->fresh()->recused_at);
        $this->assertDatabaseCount('disciplinary_case_assignments', 3);
    }

    public function test_panel_evidence_is_hidden_from_linked_admin_and_download_is_forbidden(): void
    {
        Storage::fake();
        [$event, $player, $owner] = $this->enteredPlayer();
        $owner->assignRole('admin');
        $reporter = $this->adminFor($event);
        $case = $this->reportAndTriage($event, $player, $reporter);
        Storage::put('disciplinary/private.txt', 'Private panel material');
        $evidence = $case->evidence()->create([
            'submitted_by' => $reporter->id, 'kind' => 'attachment', 'title' => 'Secret panel exhibit',
            'file_path' => 'disciplinary/private.txt', 'original_name' => 'private.txt', 'visibility' => 'panel',
        ]);
        $this->actingAs($owner)->get(route('backend.disciplinary.cases.show', $case))
            ->assertOk()->assertDontSee('Secret panel exhibit');
        $this->get(route('backend.disciplinary.cases.evidence.download', [$case, $evidence]))->assertForbidden();
        $owner->assignRole('super-user');
        $this->actingAs($owner)->get(route('backend.disciplinary.cases.show', $case))
            ->assertOk()->assertDontSee('Secret panel exhibit');
        $this->get(route('backend.disciplinary.cases.evidence.download', [$case, $evidence]))->assertForbidden();
        $unrelatedSuper = User::factory()->create()->assignRole('super-user');
        $this->actingAs($unrelatedSuper)->get(route('backend.disciplinary.cases.show', $case))
            ->assertOk()->assertSee('Secret panel exhibit');
        $this->get(route('backend.disciplinary.cases.evidence.download', [$case, $evidence]))->assertDownload('private.txt');
        $this->actingAs($reporter)->get(route('backend.disciplinary.cases.show', $case))->assertSee('Secret panel exhibit');
        $this->get(route('backend.disciplinary.cases.evidence.download', [$case, $evidence]))->assertDownload('private.txt');
        $otherCase = $this->reportAndTriage($event, $player, $reporter);
        $this->get(route('backend.disciplinary.cases.evidence.download', [$otherCase, $evidence]))->assertNotFound();
        $evidence->update(['visibility' => 'parties']);
        $this->actingAs($owner)->get(route('backend.disciplinary.cases.show', $case))->assertSee('Secret panel exhibit');
        $this->get(route('backend.disciplinary.cases.evidence.download', [$case, $evidence]))->assertDownload('private.txt');
    }

    public function test_panel_violation_cannot_be_edited_or_deleted_through_legacy_routes(): void
    {
        [$case] = $this->decidedCase();
        $violation = PlayerViolation::where('disciplinary_case_id', $case->id)->firstOrFail();
        $original = $violation->getAttributes();
        $super = User::factory()->create()->assignRole('super-user');
        $this->actingAs($super)->get(route('backend.disciplinary.violation.edit', $violation->id))->assertForbidden();
        $this->put(route('backend.disciplinary.violation.update', $violation->id), [
            'violation_type_id' => $violation->violation_type_id, 'violation_date' => today()->toDateString(),
            'points_assigned' => 0,
        ])->assertForbidden();
        $this->delete(route('backend.disciplinary.violation.destroy', $violation->id))->assertForbidden();
        $this->assertSame($original, $violation->fresh()->getAttributes());
        $this->assertDatabaseCount('player_violations', 1);
    }

    public function test_appeal_requires_decision_and_rejects_duplicates_and_final_case_even_with_stale_instance(): void
    {
        [$event, $player, $owner] = $this->enteredPlayer();
        $reporter = $this->adminFor($event);
        $case = $this->reportAndTriage($event, $player, $reporter);
        $this->actingAs($owner)->post(route('disciplinary.my-cases.appeal', $case), [
            'grounds' => 'Premature appeal must not enter the process.',
        ])->assertSessionHasErrors('appeal');
        $this->assertDatabaseCount('disciplinary_appeals', 0);
        [$decided, $owner] = $this->decidedCase();
        $stale = $decided->fresh();
        $service = app(DisciplinaryCaseService::class);
        $appeal = $service->appeal($decided, $owner, 'These are valid grounds for a single appeal.');
        try {
            $service->appeal($stale, $owner, 'A duplicate using a stale case snapshot.');
            $this->fail('Duplicate appeal must fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('disciplinary_appeals', 1);
        }
        $super = User::factory()->create()->assignRole('super-user');
        $service->decideAppeal($appeal, $super, 'confirmed', 'The original decision is confirmed after reviewing the grounds.');
        $this->actingAs($owner)->post(route('disciplinary.my-cases.appeal', $decided), [
            'grounds' => 'A repeat appeal after the final outcome.',
        ])->assertSessionHasErrors('appeal');
        $this->assertDatabaseCount('disciplinary_appeals', 1);
        $this->assertSame(DisciplinaryCase::STATUS_FINAL, $decided->fresh()->status);
    }

    public function test_unsupported_varied_appeal_is_rejected_without_changing_penalty_or_appeal(): void
    {
        [$case, $owner] = $this->decidedCase();
        $service = app(DisciplinaryCaseService::class);
        $appeal = $service->appeal($case, $owner, 'The penalty should be reconsidered on these grounds.');
        $super = User::factory()->create()->assignRole('super-user');
        $this->actingAs($super)->post(route('backend.disciplinary.appeals.decision', $appeal), [
            'outcome' => 'varied', 'reasons' => 'A variation cannot silently retain the original penalty.',
        ])->assertSessionHasErrors('outcome');
        try {
            $service->decideAppeal($appeal, $super, 'varied', 'A service caller cannot bypass variation validation.');
            $this->fail('Unsupported variation must fail.');
        } catch (ValidationException) {
            $this->assertSame('submitted', $appeal->fresh()->status);
            $this->assertSame(DisciplinaryCase::STATUS_APPEALED, $case->fresh()->status);
            $this->assertNull($case->sanctions()->firstOrFail()->revoked_at);
            $this->assertNull(PlayerViolation::where('disciplinary_case_id', $case->id)->firstOrFail()->voided_at);
            $this->assertDatabaseCount('disciplinary_sanctions', 1);
        }
    }

    public function test_triage_dismissal_and_expired_decision_cannot_be_appealed(): void
    {
        [$event, $player, $owner] = $this->enteredPlayer();
        $reporter = $this->adminFor($event);
        $service = app(DisciplinaryCaseService::class);
        $case = $service->report($event, $player, $reporter, [
            'violation_type_id' => ViolationType::first()->id,
            'incident_at' => now()->subMinute(), 'summary' => 'An allegation dismissed at initial triage.',
        ]);
        $service->triage($case, $reporter, 'dismiss', 'No grounds to proceed.');
        $this->actingAs($owner)->post(route('disciplinary.my-cases.appeal', $case), [
            'grounds' => 'Triage dismissal is not a panel decision.',
        ])->assertSessionHasErrors('appeal');
        [$decided, $owner] = $this->decidedCase();
        $decided->update(['finalized_at' => now()->subDays(15)]);
        $this->actingAs($owner)->post(route('disciplinary.my-cases.appeal', $decided), [
            'grounds' => 'An appeal outside the supported fourteen day window.',
        ])->assertSessionHasErrors('appeal');
        $this->assertDatabaseCount('disciplinary_appeals', 0);
    }

    private function members(User $chair, User $member1, User $member2): array
    {
        return [
            ['user_id' => $chair->id, 'role' => 'chair'],
            ['user_id' => $member1->id, 'role' => 'member'],
            ['user_id' => $member2->id, 'role' => 'member'],
        ];
    }

    private function decidedCase(string $type = 'suspension', string $scope = 'global'): array
    {
        [$event, $player, $owner] = $this->enteredPlayer();
        $reporter = $this->adminFor($event);
        $case = $this->reportAndTriage($event, $player, $reporter);
        $service = app(DisciplinaryCaseService::class);
        $service->submitResponse($case, $owner, 'The player response to the allegation.');
        [$chair, $member1, $member2] = $this->panelUsers();
        $service->appointPanel($case->fresh(), $reporter, $this->members($chair, $member1, $member2));
        $service->finalizeDecision($case, $chair, [
            'outcome' => 'upheld', 'reasons' => 'The panel finds the charge proven after considering the evidence.',
            'sanctions' => [['type' => $type, 'scope' => $scope, 'starts_at' => today(), 'ends_at' => today()->addMonth()]],
        ]);
        return [$case->fresh(), $owner, $chair];
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
