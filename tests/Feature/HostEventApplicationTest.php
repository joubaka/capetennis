<?php

namespace Tests\Feature;

use App\Jobs\SendHostEventApplicationNotification;
use App\Mail\HostEventApplicationMail;
use App\Models\{Event, HostEventApplication, HostEventNotification, User};
use App\Services\MailAccountManager;
use App\Services\Marketing\HostCourtCapacityPlanner;
use Illuminate\Support\Facades\{Bus, DB, File, Mail, Schema};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostEventApplicationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        foreach (['2013_01_01_000000_create_base_schema_for_testing.php', '2026_11_19_150000_create_marketing_portal_tables.php', '2026_11_19_170000_create_host_event_applications.php', '2026_11_19_180000_add_court_plan_to_host_event_applications.php'] as $file) (require database_path('migrations/'.$file))->up();
        Schema::table('category_event_registrations', fn ($table) => $table->softDeletes());
        Schema::create('event_mail_issues', function ($table) { $table->id(); $table->unsignedBigInteger('user_id'); $table->timestamp('read_at')->nullable(); });
        Schema::create('site_settings', function ($table) { $table->id(); $table->string('key')->unique(); $table->text('value')->nullable(); $table->timestamps(); });
        Schema::create('eventtypes', function ($table) { $table->id(); $table->string('name'); $table->string('type'); });
        DB::table('eventtypes')->insert(['id' => 1, 'name' => 'Individual', 'type' => 'individual']);
        Mail::fake(); Bus::fake();
    }

    private function admin(): User
    {
        Role::findOrCreate('super-user', 'web');
        return User::factory()->create()->assignRole('super-user');
    }

    private function data(User $owner): array
    {
        $token = $this->actingAs($owner)->get(route('host-events.create'))->assertOk()->viewData('token');
        $data = ['submission_token' => $token, 'event_name' => 'Fictional Stage One', 'academy' => 'Example Academy',
            'contact_name' => 'Example Coach', 'email' => 'COACH@example.test', 'phone' => '+27 82 000 0000',
            'town' => 'Test Town', 'venue' => 'Test Club', 'start_date' => now()->addDays(14)->toDateString(),
            'end_date' => now()->addDays(14)->toDateString(), 'courts' => 10, 'assigned_courts' => [1, 4, 3, 2],
            'limits' => [16, 16, 12, 8], 'fees' => ['200', '250', '250', '300'], 'notes' => '<script>alert(1)</script>'];
        return $data;
    }

    private function submit(User $owner): HostEventApplication
    {
        $this->post(route('host-events.store'), $this->data($owner))->assertRedirect();
        return HostEventApplication::latest('id')->firstOrFail();
    }

    private function eventData(HostEventApplication $application): array
    {
        return ['host_application_id' => $application->id, 'name' => 'Reviewed local event',
            'start_date' => $application->start_date->toDateString(), 'end_date' => $application->end_date->toDateString(),
            'eventType' => 1, 'entryFee' => 0, 'deadline' => 7, 'published' => 1, 'signUp' => 1];
    }

    public function test_public_landing_authentication_and_applicant_isolation(): void
    {
        $this->get(route('host-events.index'))->assertOk()->assertSee('Host small.');
        $this->get(route('host-events.create'))->assertRedirect();
        $this->post(route('host-events.store'), [])->assertRedirect();
        $owner = User::factory()->create();
        $application = $this->submit($owner);
        $this->get(route('host-events.show', $application))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('host-events.show', $application))->assertForbidden();
        Role::findOrCreate('admin', 'web');
        $ordinaryAdmin = User::factory()->create()->assignRole('admin');
        $this->actingAs($ordinaryAdmin)->get(route('superadmin.marketing.applications.index'))->assertForbidden();
        $this->get(route('superadmin.marketing.applications.show', $application))->assertForbidden();
        $this->post(route('superadmin.marketing.applications.review', $application), ['decision' => 'approved', 'review_notes' => 'Forged', 'confirm' => 1])->assertForbidden();
        $this->assertSame('pending', $application->fresh()->status);
    }

    public function test_submission_is_idempotent_ignores_privileged_fields_and_only_queues_super_user_alerts(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        $data = $this->data($owner) + ['user_id' => $admin->id, 'status' => 'approved', 'event_id' => 55, 'published' => 1, 'reviewed_by' => $owner->id];
        $this->post(route('host-events.store'), $data)->assertRedirect();
        $this->post(route('host-events.store'), array_replace($data, ['event_name' => 'Retry changed name']))->assertRedirect();
        $this->assertDatabaseCount('host_event_applications', 1);
        $this->assertDatabaseCount('host_event_notifications', 1);
        $application = HostEventApplication::sole();
        $this->assertSame($owner->id, $application->user_id);
        $this->assertSame('pending', $application->status);
        $this->assertSame('Fictional Stage One', $application->event_name);
        $this->assertSame('coach@example.test', $application->email);
        $this->assertNull($application->event_id);
        $this->assertNull($application->reviewed_by);
        $this->assertSame([16, 16, 12, 8], array_column($application->requested_categories, 'limit'));
        $this->assertSame('court-ratio-v2', $application->court_plan['model']);
        $this->assertSame(0, $owner->roles()->count());
        $this->assertDatabaseCount('events', 0);
        Bus::assertDispatched(SendHostEventApplicationNotification::class, fn ($job) => $job->afterCommit === true && $job->connection === 'database');
        Bus::assertDispatchedTimes(SendHostEventApplicationNotification::class, 1);
        Mail::assertNothingSent();
    }

    public function test_expired_or_forged_tokens_and_bad_dates_capacity_and_fees_are_rejected(): void
    {
        $owner = User::factory()->create();
        $data = $this->data($owner);
        $this->post(route('host-events.store'), array_replace($data, ['submission_token' => (string) \Illuminate\Support\Str::uuid()]))->assertStatus(419);
        $this->post(route('host-events.store'), array_replace($data, ['end_date' => now()->subDay()->toDateString(), 'courts' => 0, 'fees' => ['-1', 250, 250, 300]]))->assertSessionHasErrors(['end_date', 'courts', 'fees.0']);
        $this->post(route('host-events.store'), array_replace($data, ['limits' => [0, 0, 0, 0]]))->assertSessionHasErrors('limits');
        $this->assertDatabaseCount('host_event_applications', 0);
        Bus::assertNothingDispatched();
    }

    public function test_submission_token_is_actor_bound_and_unapproved_host_id_cannot_be_forged_by_an_admin(): void
    {
        $owner = User::factory()->create();
        $data = $this->data($owner);
        $other = User::factory()->create();
        $this->actingAs($other)->post(route('host-events.store'), $data)->assertStatus(419);
        $this->actingAs($owner)->post(route('host-events.store'), $data)->assertRedirect();
        $application = HostEventApplication::sole();
        Role::findOrCreate('admin', 'web');
        $other->assignRole('admin');
        $this->actingAs($other)->post(route('backend.events.store'), $this->eventData($application))->assertForbidden();
        $this->get(route('backend.events.create', ['host_application' => $application->id]))->assertForbidden();
        $this->assertDatabaseCount('events', 0);
        $this->assertSame('pending', $application->fresh()->status);
    }

    public function test_rolled_back_submission_does_not_dispatch_a_review_notification(): void
    {
        $this->admin();
        $owner = User::factory()->create();
        $data = $this->data($owner);
        DB::beginTransaction();
        $this->post(route('host-events.store'), $data)->assertRedirect();
        Bus::assertNothingDispatched();
        DB::rollBack();
        $this->assertDatabaseCount('host_event_applications', 0);
        $this->assertDatabaseCount('host_event_notifications', 0);
        Bus::assertNothingDispatched();
        Mail::assertNothingSent();
    }

    public function test_super_user_review_then_canonical_creation_is_unpublished_and_replay_safe(): void
    {
        $admin = $this->admin();
        $application = $this->submit(User::factory()->create());
        $this->actingAs($admin)->get(route('backend.events.create', ['host_application' => $application->id]))->assertStatus(409);
        $this->post(route('backend.events.store'), $this->eventData($application))->assertStatus(409);
        $this->assertDatabaseCount('events', 0);
        $review = ['decision' => 'approved', 'review_notes' => 'Courts, dates and organiser verified.', 'confirm' => 1];
        $this->post(route('superadmin.marketing.applications.review', $application), $review)->assertRedirect();
        $this->post(route('superadmin.marketing.applications.review', $application), $review)->assertRedirect();
        $application->refresh();
        $this->assertSame($admin->id, $application->reviewed_by);
        $this->fixture('review-approved', $this->get(route('superadmin.marketing.applications.show', $application))->assertOk());
        $this->fixture('event-create', $this->get(route('backend.events.create', ['host_application' => $application->id]))->assertOk()->assertSee('Fictional Stage One')->assertSee('host_application_id'));
        $this->post(route('backend.events.store'), $this->eventData($application))->assertRedirect();
        $this->post(route('backend.events.store'), $this->eventData($application))->assertRedirect();
        $this->assertDatabaseCount('events', 1);
        $event = Event::sole();
        $this->assertFalse($event->published);
        $this->assertFalse($event->signUp);
        $this->assertSame($event->id, $application->fresh()->event_id);
        $this->assertSame(0, $event->admins()->count());
        $this->assertDatabaseCount('category_events', 0);
        $this->get(route('superadmin.marketing.applications.show', $application))->assertOk()->assertSee('Configure event and publication');
        $this->patch(route('backend.events.update', $event), $this->eventData($application) + ['admins' => []])->assertOk();
        $this->assertTrue($event->fresh()->published);
        Mail::assertNothingSent();
    }

    public function test_rejected_proposal_cannot_create_an_event_and_normal_creation_is_unchanged(): void
    {
        $admin = $this->admin();
        $application = $this->submit(User::factory()->create());
        $this->actingAs($admin)->post(route('superadmin.marketing.applications.review', $application), ['decision' => 'rejected', 'review_notes' => 'Venue unavailable.', 'confirm' => 1])->assertRedirect();
        $this->post(route('backend.events.store'), $this->eventData($application))->assertStatus(409);
        $normal = $this->eventData($application);
        unset($normal['host_application_id']);
        $this->post(route('backend.events.store'), $normal)->assertRedirect();
        $this->assertTrue(Event::sole()->published);
        $this->assertTrue(Event::sole()->signUp);
        $this->assertNull($application->fresh()->event_id);
    }

    public function test_notification_claim_is_idempotent_and_rechecks_current_super_user_privilege(): void
    {
        $admin = $this->admin();
        $application = $this->submit(User::factory()->create());
        $notification = HostEventNotification::sole();
        $accounts = \Mockery::mock(MailAccountManager::class);
        $accounts->shouldReceive('getMailer')->andReturn('array');
        $job = new SendHostEventApplicationNotification($notification->id);
        $job->handle($accounts); $job->handle($accounts);
        Mail::assertSent(HostEventApplicationMail::class, 1);
        $this->assertSame('sent', $notification->fresh()->status);
        $next = HostEventNotification::create(['application_id' => $application->id + 1, 'admin_id' => $admin->id]);
        $admin->removeRole('super-user');
        (new SendHostEventApplicationNotification($next->id))->handle($accounts);
        $this->assertSame('skipped', $next->fresh()->status);
        Mail::assertSent(HostEventApplicationMail::class, 1);
    }

    public function test_notification_transport_failure_is_recorded_and_not_automatically_replayed(): void
    {
        $this->admin();
        $this->submit(User::factory()->create());
        $notification = HostEventNotification::sole();
        $accounts = \Mockery::mock(MailAccountManager::class);
        $accounts->shouldReceive('getMailer')->once()->andThrow(new \RuntimeException('Simulated transport unavailable'));
        $job = new SendHostEventApplicationNotification($notification->id);
        try {
            $job->handle($accounts);
            $this->fail('Expected transport failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated transport unavailable', $exception->getMessage());
        }
        $this->assertSame('needs_review', $notification->fresh()->status);
        $job->handle($accounts);
        Mail::assertNothingSent();
    }

    public function test_fictional_host_workflow_browser_fixtures(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        $data = $this->data($owner);
        $this->post(route('host-events.store'), array_replace($data, ['event_name' => str_repeat('Event', 45), 'venue' => str_repeat('Venue', 45), 'town' => str_repeat('Town', 45)]))->assertRedirect();
        $application = HostEventApplication::sole();
        foreach (['landing' => route('host-events.index'), 'form' => route('host-events.create'), 'status' => route('host-events.show', $application)] as $name => $url) $this->fixture($name, $this->get($url)->assertOk());
        $this->actingAs($admin);
        $this->fixture('inbox', $this->get(route('superadmin.marketing.applications.index'))->assertOk());
        $this->fixture('review', $this->get(route('superadmin.marketing.applications.show', $application))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false));
    }

    public function test_court_ratios_scale_without_stage_one_caps(): void
    {
        $planner = app(HostCourtCapacityPlanner::class);
        $this->assertSame([16, 16, 12, 8], $planner->calculate($planner->defaults())['limits']);
        $expanded = $planner->calculate(['courts' => 12, 'assigned_courts' => [2, 5, 3, 2]]);
        $this->assertSame([32, 20, 12, 8], $expanded['limits']);
        $this->assertSame(72, $expanded['suggested_entries']);
        $this->assertSame(12, $expanded['assigned_total']);
        $single = $planner->calculate(['courts' => 3, 'assigned_courts' => [2, 0, 0, 0]]);
        $this->assertSame([32, 0, 0, 0], $single['limits']);
        $this->assertSame(1, $single['unassigned_courts']);
        $this->assertSame([1600, 0, 0, 0], $planner->calculate(['courts' => 100, 'assigned_courts' => [100, 0, 0, 0]])['limits']);
    }

    public function test_ajax_suggestions_require_auth_and_actor_token_and_create_no_records_or_review_state(): void
    {
        $this->postJson(route('host-events.suggestions'), [])->assertUnauthorized();
        $data = $this->data(User::factory()->create());
        $this->postJson(route('host-events.suggestions'), array_replace($data, ['courts' => 11, 'assigned_courts' => [2, 4, 3, 2], 'limits' => [999, 999, 999, 999]]))
            ->assertOk()->assertJsonPath('model', 'court-ratio-v2')->assertJsonPath('limits.0', 32)->assertSessionMissing('host_court_plans');
        $this->postJson(route('host-events.suggestions'), array_replace($data, ['submission_token' => (string) \Illuminate\Support\Str::uuid()]))->assertStatus(419);
        $this->assertDatabaseCount('host_event_applications', 0);
        $this->assertDatabaseCount('host_event_notifications', 0);
        Bus::assertNothingDispatched(); Mail::assertNothingSent();
    }

    public function test_submit_recomputes_current_ratios_and_rejects_forged_limits_and_nonmultiple_groups(): void
    {
        $data = $this->data(User::factory()->create());
        $this->post(route('host-events.store'), array_replace($data, ['limits' => [20, 16, 12, 8], 'court_plan' => ['limits' => [1000, 1000, 1000, 1000]]]))->assertSessionHasErrors('limits.0');
        $this->post(route('host-events.store'), array_replace($data, ['limits' => [16, 16, 12, 7]]))->assertSessionHasErrors('limits.3');
        $this->post(route('host-events.store'), array_replace($data, ['assigned_courts' => [0, 4, 3, 2], 'limits' => [4, 16, 12, 8]]))->assertSessionHasErrors('limits.0');
        $this->assertDatabaseCount('host_event_applications', 0);
        Bus::assertNothingDispatched();
    }

    public function test_submit_accepts_increased_courts_without_prior_ajax_and_saves_authoritative_snapshot(): void
    {
        $data = $this->data(User::factory()->create());
        $this->post(route('host-events.store'), array_replace($data, ['courts' => 11, 'assigned_courts' => [2, 4, 3, 2], 'limits' => [32, 16, 12, 8], 'court_plan' => ['model' => 'forged']]))->assertRedirect();
        $application = HostEventApplication::sole();
        $this->assertSame('court-ratio-v2', $application->court_plan['model']);
        $this->assertSame(32, $application->requested_categories[0]['limit']);
        $this->assertSame([2, 4, 3, 2], $application->court_plan['inputs']['assigned_courts']);
        $this->assertSame(68, $application->court_plan['suggested_entries']);
    }

    public function test_overallocated_empty_or_malformed_assignments_are_rejected_without_side_effects(): void
    {
        $data = $this->data(User::factory()->create());
        foreach ([['courts' => 1], ['assigned_courts' => [0, 0, 0, 0]], ['assigned_courts' => [-1, 4, 3, 2]], ['courts' => 101], ['assigned_courts' => [1, 2]], ['assigned_courts' => ['red' => 1, 'orange' => 4, 'green' => 3, 'yellow' => 2]]] as $change) {
            $this->postJson(route('host-events.suggestions'), array_replace($data, $change))->assertUnprocessable();
        }
        $this->post(route('host-events.store'), array_replace($data, ['courts' => 1]))->assertRedirect(route('host-events.create'))->assertSessionHasErrors('assigned_courts');
        $this->assertDatabaseCount('host_event_applications', 0);
        Bus::assertNothingDispatched();
    }

    public function test_legacy_time_snapshots_and_null_plans_remain_readable_without_rewriting(): void
    {
        $owner = User::factory()->create();
        $application = $this->submit($owner);
        $legacy = ['model' => 'four-player-rr-knockout-v1', 'inputs' => ['courts' => 8, 'hours_hundredths' => 400, 'slot_minutes' => 30, 'contingency_percent' => 20],
            'suggested_entries' => 28, 'planned_matches' => 45, 'usable_minutes' => 1536];
        $application->update(['court_plan' => $legacy]);
        $this->get(route('host-events.show', $application))->assertOk()->assertSee('Historical time-based estimate');
        $this->assertSame($legacy, $application->fresh()->court_plan);
        $application->update(['court_plan' => null]);
        $this->get(route('host-events.show', $application))->assertOk()->assertSee('No court-capacity estimate was recorded');
    }

    public function test_malformed_inputs_return_json_or_a_usable_form_and_categories_are_visible_initially(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->get(route('host-events.create'))->assertOk()->assertSee('Suggested entries')->assertDontSee('Calculate suggested entry limits');
        $this->postJson(route('host-events.suggestions'), ['submission_token' => ['bad']])->assertUnprocessable()->assertJsonValidationErrors('submission_token');
        $this->post(route('host-events.store'), ['submission_token' => ['bad'], 'courts' => ['bad']])->assertRedirect(route('host-events.create'))->assertSessionHasErrors('submission_token');
        $this->get(route('host-events.create'))->assertOk()->assertSee('Total available full-size courts');
        $this->assertDatabaseCount('host_event_applications', 0);
        Bus::assertNothingDispatched();
    }

    public function test_live_financial_default_is_exact_and_commercial_overrides_are_ignored(): void
    {
        $data = $this->data(User::factory()->create());
        $response = $this->get(route('host-events.create'))->assertOk()->assertSee('R8,690.00');
        $totals = $response->viewData('projection')['totals'];
        $this->assertSame(['entries' => 52, 'gross' => 1260000, 'processing' => 63000, 'platform' => 78000, 'retained' => 1119000,
            'convenor' => 50000, 'rental' => 200000, 'other_costs' => 0, 'remaining' => 869000], $totals);
        $this->postJson(route('host-events.suggestions'), $data + ['processing_percent' => 0, 'platform_fee' => 0, 'cape_tennis_fee' => 0, 'remaining' => 999999])
            ->assertOk()->assertJsonPath('financial_projection.totals.remaining', 869000)
            ->assertJsonPath('financial_projection.assumptions.processing_percent', '5.00')
            ->assertJsonPath('financial_projection.assumptions.platform_fee', '15.00');
        $this->assertDatabaseCount('host_event_applications', 0);
        Bus::assertNothingDispatched(); Mail::assertNothingSent();
    }

    public function test_manual_entry_reduction_fees_and_expenses_use_assigned_courts_and_exact_cents(): void
    {
        $data = $this->data(User::factory()->create());
        $input = array_replace($data, ['courts' => 20, 'limits' => [8, 16, 12, 8], 'fees' => ['200.10', '250', '250', '300'],
            'convenor_cost' => '600', 'rental_hours' => '3.50', 'rental_rate' => '60', 'other_costs' => '100', 'use_suggested_limits' => false]);
        $this->postJson(route('host-events.suggestions'), $input)->assertOk()
            ->assertJsonPath('financial_projection.totals.entries', 44)
            ->assertJsonPath('financial_projection.totals.gross', 1100080)
            ->assertJsonPath('financial_projection.totals.processing', 55008)
            ->assertJsonPath('financial_projection.totals.platform', 66000)
            ->assertJsonPath('financial_projection.assumptions.courts', 10)
            ->assertJsonPath('financial_projection.totals.rental', 210000)
            ->assertJsonPath('financial_projection.totals.remaining', 699072);
        $this->post(route('host-events.store'), $input + ['financial_projection' => ['totals' => ['remaining' => 999999]], 'processing_percent' => 0])->assertRedirect();
        $saved = HostEventApplication::sole()->court_plan['financial_projection'];
        $this->assertSame(699072, $saved['totals']['remaining']);
        $this->assertSame([8, 16, 12, 8], $saved['assumptions']['entries']);
        $this->assertSame('5.00', $saved['assumptions']['processing_percent']);
    }

    public function test_zero_entries_can_preview_a_loss_but_cannot_submit_and_maximum_red_projection_works(): void
    {
        $data = $this->data(User::factory()->create());
        $this->postJson(route('host-events.suggestions'), array_replace($data, ['limits' => [0, 0, 0, 0], 'use_suggested_limits' => false]))
            ->assertOk()->assertJsonPath('financial_projection.totals.remaining', -250000);
        $this->post(route('host-events.store'), array_replace($data, ['limits' => [0, 0, 0, 0]]))->assertSessionHasErrors('limits');
        $this->postJson(route('host-events.suggestions'), array_replace($data, ['courts' => 100, 'assigned_courts' => [100, 0, 0, 0]]))
            ->assertOk()->assertJsonPath('financial_projection.totals.entries', 1600)
            ->assertJsonPath('financial_projection.totals.gross', 32000000)
            ->assertJsonPath('financial_projection.totals.remaining', 25950000);
        $this->assertDatabaseCount('host_event_applications', 0);
        Bus::assertNothingDispatched();
    }

    public function test_invalid_financial_inputs_fail_without_records_or_notification(): void
    {
        $data = $this->data(User::factory()->create());
        foreach ([['convenor_cost' => '0.001'], ['rental_hours' => 25], ['rental_rate' => -1], ['other_costs' => '1e3'], ['fees' => ['200.001', '250', '250', '300']]] as $change) {
            $this->postJson(route('host-events.suggestions'), array_replace($data, $change))->assertUnprocessable();
        }
        $this->assertDatabaseCount('host_event_applications', 0);
        $this->assertDatabaseCount('host_event_notifications', 0);
        Bus::assertNothingDispatched(); Mail::assertNothingSent();
    }

    private function fixture(string $name, $response): void
    {
        if (getenv('CT_HOST_QA_HTML') !== '1') return;
        File::ensureDirectoryExists(storage_path('framework/testing'));
        file_put_contents(storage_path('framework/testing/host-event-'.$name.'.html'), $response->getContent());
    }
}
