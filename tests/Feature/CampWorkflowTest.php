<?php

namespace Tests\Feature;

use App\Jobs\SendCampInvitation;
use App\Models\{BulkEmailLog, CampCoachLink, CampCoachSubmission, CampConfiguration, CampNominee, CategoryEvent, Event, Player, PracticeCampPlan, User};
use App\Services\Camps\{CampAdmission, CampInvitations, CampWorkflow};
use App\Services\PracticeCampPlanner;
use Illuminate\Support\Facades\{Bus, DB, Mail, Schema};
use Spatie\Permission\Models\Role;

/** Isolated SQLite fixtures: no application/local/production database is refreshed. */
class CampWorkflowTest extends PracticeCampPlannerTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-11-20 10:00:00'));
        (require database_path('migrations/2026_11_20_160000_create_camp_workflows.php'))->up();
        (require database_path('migrations/2026_06_18_112113_create_bulk_email_logs_table.php'))->up();
        Schema::table('registration_orders', fn ($t) => $t->string('status')->nullable());
        Schema::table('category_events', fn ($t) => $t->unsignedInteger('entry_limit')->nullable());
        Schema::table('players', function ($t) { $t->string('email_2')->nullable(); $t->string('email_3')->nullable(); });
        Schema::create('category_adjustments', function ($t) { $t->id(); $t->unsignedBigInteger('entry_id'); $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('player_id'); $t->unsignedBigInteger('target_category_id'); $t->string('status'); });
    }

    private function actor(): User
    {
        Role::findOrCreate('super-user', 'web');
        return User::factory()->create()->assignRole('super-user');
    }

    private function settingsData(PracticeCampPlan $plan, string $method = 'invitation_only'): array
    {
        return ['revision' => $plan->fresh()->revision, 'name' => 'Fictional Camp', 'start_date' => '2027-01-07', 'end_date' => '2027-01-09',
            'dates_provisional' => true, 'venue' => 'Example Courts', 'organizer' => 'Example Organiser', 'email' => 'organiser@example.test',
            'entry_fee' => '100.25', 'capacity' => 20, 'method' => $method, 'registration_closes_at' => '2027-01-02 17:00:00',
            'coach_closes_at' => '2026-12-20 17:00:00', 'withdrawal_deadline' => '2027-01-01 17:00:00', 'coach_min_age' => 8, 'coach_max_age' => 9,
            'divisions' => [['name' => 'U9 and U10', 'min_age' => 9, 'max_age' => 10, 'gender' => 0, 'fee' => '100.25', 'limit' => 20]]];
    }

    private function configured(string $method = 'invitation_only'): array
    {
        $actor = $this->actor();
        $plan = app(PracticeCampPlanner::class)->create2027($actor);
        app(CampWorkflow::class)->settings($actor, $plan, $this->settingsData($plan, $method));
        $plan = $plan->fresh();
        $plan->event->update(['published' => true, 'signUp' => true]);
        $division = CategoryEvent::where('event_id', $plan->event_id)->firstOrFail();
        return [$actor, $plan, $division];
    }

    private function child(array $data = []): Player
    {
        return Player::factory()->create([...['name' => 'Example', 'surname' => 'Child', 'dateOfBirth' => '2018-04-05', 'gender' => 1,
            'email' => 'parent@example.test', 'email_2' => 'COPY@example.test'], ...$data]);
    }

    public function test_roster_batch_requires_current_exact_candidates_and_atomic_explicit_review_without_sending(): void
    {
        [$actor, $plan, $division] = $this->configured();
        $first = $this->child(); $second = $this->child(['name' => 'Other', 'dateOfBirth' => '2017-03-04']);
        $plan->update(['candidates' => collect([$first, $second])->map(fn ($p) => ['key' => 'player:'.$p->id, 'player_id' => $p->id, 'name' => $p->full_name, 'sources' => []])->push(['key' => 'imported:1:1', 'player_id' => null, 'name' => 'Unresolved', 'sources' => []])->all()]);
        $url = route('backend.camps.approve-roster', $plan);
        $data = ['revision' => $plan->revision, 'keys' => ['player:'.$first->id, 'player:'.$second->id],
            'division_ids' => [$first->id => $division->id, $second->id => $division->id], 'reason' => 'Reviewed roster', 'identity_confirmed' => 1];
        $this->actingAs(User::factory()->create())->post($url, $data)->assertForbidden();
        $this->actingAs($actor)->post($url, array_diff_key($data, ['identity_confirmed' => 1]))->assertSessionHasErrors('identity_confirmed');
        $this->post($url, [...$data, 'revision' => (string) \Illuminate\Support\Str::uuid()])->assertConflict();
        $this->withHeader('Sec-Fetch-Mode', 'navigate')->post($url, [...$data, 'revision' => (string) \Illuminate\Support\Str::uuid()])
            ->assertRedirect(route('backend.camps.show', $plan))->assertSessionHasErrors('camp');
        $this->flushHeaders();
        $this->withHeader('Sec-Fetch-Mode', 'navigate')->postJson($url, [...$data, 'revision' => (string) \Illuminate\Support\Str::uuid()])->assertConflict();
        $this->flushHeaders();
        $this->post($url, [...$data, 'keys' => ['player:'.$first->id, 'imported:1:1']])->assertUnprocessable();
        $this->assertDatabaseCount('camp_nominees', 0);
        $this->post($url, [...$data, 'division_ids' => [$first->id => $division->id, $second->id => 999999]])->assertNotFound();
        $this->assertDatabaseCount('camp_nominees', 0);
        $this->post($url, $data)->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseCount('camp_nominees', 2);
        $this->post($url, $data)->assertRedirect();
        $this->assertDatabaseCount('camp_nominees', 2);
        $this->assertDatabaseCount('user_players', 0);
        $this->get(route('backend.camps.show', $plan))->assertOk()->assertSee('Approve selected roster players')->assertSee('Select all ready nominees');
        Mail::assertNothingSent(); Bus::assertNothingDispatched();
    }

    public function test_settings_methods_dates_fees_and_withdrawal_deadline_do_not_publish_or_open_entries(): void
    {
        $actor = $this->actor(); $plan = app(PracticeCampPlanner::class)->create2027($actor);
        $plan->event->update(['eventType' => 1]); // Earlier private drafts used an Individual fallback.
        $data = $this->settingsData($plan);
        $response = $this->actingAs($actor)->put(route('backend.camps.settings', $plan), $data);
        $response->assertRedirect();
        $this->assertFalse($plan->fresh()->event->published);
        $this->assertFalse($plan->fresh()->event->signUp);
        $this->assertSame('2027-01-01 17:00:00', $plan->fresh()->event->withdrawal_deadline->format('Y-m-d H:i:s'));
        $this->assertSame('100.25', (string) CategoryEvent::where('event_id', $plan->event_id)->value('entry_fee'));
        $this->assertSame('invitation_only', CampConfiguration::first()->method);
        $this->assertTrue($plan->fresh()->event->isCamp());
        $this->put(route('backend.camps.settings', $plan), $data)->assertConflict();
        $this->get(route('backend.camps.show', $plan))->assertOk()->assertSee('Approved nominees only')->assertSee('Whole-camp player capacity');
        Mail::assertNothingSent(); Bus::assertNothingDispatched();
    }

    public function test_methods_require_exact_approved_division_only_for_invitation_only_and_never_account_link(): void
    {
        [$actor, $plan, $division] = $this->configured(); $player = $this->child();
        $admission = app(CampAdmission::class);
        try { $admission->assertEligible($division, $player->id); $this->fail('Unapproved nominee admitted'); } catch (\RuntimeException $e) { $this->assertStringContainsString('approved nominee', $e->getMessage()); }
        $nominee = app(CampWorkflow::class)->approve($actor, $plan, $player->id, $division->id, 'Reviewed team evidence');
        $admission->assertEligible($division, $player->id);
        $this->assertDatabaseCount('user_players', 0);
        $nominee->update(['status' => 'declined']);
        foreach (['open', 'open_with_invitations'] as $method) {
            CampConfiguration::where('event_id', $plan->event_id)->update(['method' => $method]);
            $admission->assertEligible($division, $player->id);
        }
        $player->update(['dateOfBirth' => '2010-04-05']);
        $this->expectException(\RuntimeException::class);
        $admission->assertEligible($division, $player->id);
    }

    public function test_legacy_camp_classification_preserves_other_families_and_unconfigured_admission(): void
    {
        DB::table('eventtypes')->insert(['id' => 2, 'name' => 'Camp', 'type' => 0]);
        $camp = Event::factory()->create(['eventType' => 2]);
        $this->assertTrue($camp->isCamp()); $this->assertTrue($camp->isIndividual());
        $this->assertSame('individual', \App\Support\EventTypePresentation::view($camp));
        $other = Event::factory()->create(['eventType' => 1]);
        $this->assertFalse($other->isCamp());
        $category = CategoryEvent::create(['event_id' => $camp->id, 'category_id' => \App\Models\Category::create(['name' => 'Open'])->id]);
        app(CampAdmission::class)->assertEligible($category, $this->child(['dateOfBirth' => null])->id);
        $actor = $this->actor();
        $this->actingAs($actor)->post(route('backend.camps.attach', $camp))->assertRedirect();
        $this->assertDatabaseCount('camp_configurations', 0);
        $actor = $this->actor();
        $plan = app(CampWorkflow::class)->attach($actor, $camp);
        $this->assertSame((float) $camp->entryFee, (float) $plan->entry_fee);
        app(CampAdmission::class)->assertEligible($category, 999999);
        $this->assertDatabaseCount('camp_configurations', 0);
    }

    public function test_prepared_draft_with_unknown_fee_cannot_be_accidentally_opened_as_free_entry(): void
    {
        $plan = app(PracticeCampPlanner::class)->create2027($this->actor());
        $plan->event->update(['published' => true, 'signUp' => true]);
        $category = CategoryEvent::create(['event_id' => $plan->event_id]);
        $this->expectException(\RuntimeException::class);
        app(CampAdmission::class)->assertEligible($category, 999999);
    }

    public function test_exact_configured_deadline_overrides_legacy_day_offset_in_both_directions_and_at_the_hour(): void
    {
        [, $plan, $division] = $this->configured('open');
        $plan->event->update(['deadline' => 5]); // Legacy deadline would be 2 January.
        CampConfiguration::where('event_id', $plan->event_id)->update(['registration_closes_at' => '2027-01-04 15:00:00']);
        $this->travelTo(\Carbon\Carbon::parse('2027-01-03 10:00:00'));
        $this->assertTrue($plan->event->fresh()->registrationIsOpen());
        $this->assertSame('2027-01-04 15:00:00', $plan->event->fresh()->registrationClosesAt()->format('Y-m-d H:i:s'));
        app(CampAdmission::class)->assertEligible($division->fresh(), $this->child()->id);
        $this->travelTo(\Carbon\Carbon::parse('2027-01-04 15:00:01'));
        $this->assertFalse($plan->event->fresh()->registrationIsOpen());
        $plan->event->update(['deadline' => 0]); // Legacy deadline would be 7 January.
        CampConfiguration::where('event_id', $plan->event_id)->update(['registration_closes_at' => '2027-01-01 11:00:00']);
        $this->travelTo(\Carbon\Carbon::parse('2027-01-01 11:00:01'));
        $this->assertFalse($plan->event->fresh()->registrationIsOpen());
    }

    public function test_coach_tokens_are_scoped_revocable_expiring_and_submissions_remain_pending_and_idempotent(): void
    {
        [$actor, $plan, $division] = $this->configured();
        $url = app(CampWorkflow::class)->coach($actor, $plan, 'Example Coach', '2026-12-15 17:00:00');
        $token = basename($url);
        $this->get($url)->assertOk()->assertSee('Example Coach')->assertDontSee('parent@example.test');
        $payload = ['request_token' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Example', 'surname' => 'Child', 'date_of_birth' => '2018-04-05',
            'gender' => 1, 'guardian_name' => 'Example Parent', 'guardian_email' => 'PARENT@example.test', 'reason' => 'Ready for the camp'];
        $this->post($url, $payload)->assertRedirect(); $this->post($url, $payload)->assertRedirect();
        $this->assertDatabaseCount('camp_coach_submissions', 1); $this->assertDatabaseCount('camp_nominees', 0); $this->assertDatabaseCount('players', 0);
        $link = CampCoachLink::first(); $this->assertNotSame($token, $link->token_hash);
        $link->update(['revoked_at' => now()]); $this->get($url)->assertGone();
        $link->update(['revoked_at' => null, 'expires_at' => now()->subMinute()]); $this->get($url)->assertGone();
        $this->assertDatabaseCount('bulk_email_logs', 0); Mail::assertNothingSent();
    }

    public function test_organiser_can_review_coach_create_or_resolve_exact_player_without_ownership_or_automatic_email(): void
    {
        [$actor, $plan, $division] = $this->configured();
        $url = app(CampWorkflow::class)->coach($actor, $plan, 'Example Coach', '2026-12-15 17:00:00');
        app(CampWorkflow::class)->submitCoach(basename($url), ['request_token' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'New', 'surname' => 'Child',
            'date_of_birth' => '2018-04-05', 'gender' => 1, 'guardian_name' => 'Parent', 'guardian_email' => 'parent@example.test']);
        $submission = CampCoachSubmission::first();
        $this->actingAs($actor)->post(route('backend.camps.review-coach', [$plan, $submission]), [
            'decision' => 'approve', 'create_new' => 1, 'division_id' => $division->id, 'identity_confirmed' => 1,
        ])->assertRedirect();
        $this->assertSame('approved', $submission->fresh()->status);
        $this->assertDatabaseCount('players', 1); $this->assertDatabaseCount('camp_nominees', 1); $this->assertDatabaseCount('user_players', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0); Mail::assertNothingSent();
        $this->post(route('backend.camps.review-coach', [$plan, $submission]), ['decision' => 'approve', 'create_new' => 1, 'division_id' => $division->id, 'identity_confirmed' => 1])->assertRedirect();
        $this->assertDatabaseCount('players', 1);
        app(CampWorkflow::class)->submitCoach(basename($url), ['request_token' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Rejected', 'surname' => 'Child', 'date_of_birth' => '2018-05-01', 'gender' => 1, 'guardian_name' => 'Parent', 'guardian_email' => 'parent@example.test']);
        $pending = CampCoachSubmission::where('status', 'pending')->firstOrFail();
        if (getenv('CAMP_WORKFLOW_RENDER_FIXTURES')) {
            $nominee = CampNominee::firstOrFail();
            $ready = $this->child(['name' => 'Ready']);
            Player::factory()->create(['name' => $pending->name, 'surname' => $pending->surname, 'dateOfBirth' => $pending->date_of_birth->toDateString(), 'gender' => $pending->gender]);
            $plan->update(['candidates' => [
                ['key' => 'player:'.$nominee->player_id, 'name' => $nominee->player->full_name, 'player_id' => $nominee->player_id, 'identity' => 'Existing player', 'sources' => []],
                ['key' => 'player:'.$ready->id, 'name' => $ready->full_name, 'player_id' => $ready->id, 'identity' => 'Existing player', 'sources' => []],
                ['key' => 'imported:999:1', 'name' => 'Fictional Imported Child', 'player_id' => null, 'identity' => 'Imported identity — needs matching', 'sources' => []],
            ]]);
            $responses = [
                'manage' => $this->get(route('backend.camps.show', $plan).'?player_search=Child')->assertOk(),
                'coach' => $this->get($url)->assertOk(),
                'invitation' => $this->get(route('camp.invitation.show', $nominee->token_encrypted))->assertOk(),
                'mail-review' => $this->post(route('backend.camps.mail-review', $plan), ['ids' => [$nominee->id], 'subject' => 'Fictional camp invitation', 'message' => 'Please review our fictional practice camp details.'])->assertOk(),
            ];
            $this->post(route('backend.camps.coach', $plan), ['_camp_form' => route('backend.camps.coach', $plan)])->assertSessionHasErrors('coach_name');
            $responses['manage-error'] = $this->get(route('backend.camps.show', $plan))->assertOk();
            $directory = storage_path('framework/testing/camp-workflow-fixtures');
            if (! is_dir($directory)) mkdir($directory, 0777, true);
            foreach ($responses as $name => $response) file_put_contents($directory.'/'.$name.'.html', $response->getContent());
        }
        $this->post(route('backend.camps.review-coach', [$plan, $pending]), ['decision' => 'reject'])->assertRedirect();
        $this->assertSame('rejected', $pending->fresh()->status);
    }

    public function test_reviewed_invitation_mail_is_exact_stale_checked_actor_scoped_and_idempotent(): void
    {
        [$actor, $plan, $division] = $this->configured(); $player = $this->child();
        $nominee = app(CampWorkflow::class)->approve($actor, $plan, $player->id, $division->id, 'Reviewed');
        $service = app(CampInvitations::class);
        $review = $service->review($actor, $plan, [$nominee->id], 'Camp invitation', 'Please join us.');
        $this->assertCount(2, $review->snapshot['rows']);
        $this->assertStringContainsString('/camp-invitations/', $review->snapshot['rows'][0]['html']);
        $this->assertStringNotContainsString('/camp-invitations/', $review->snapshot['rows'][1]['html']);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $service->queue($actor, $plan, $review); $service->queue($actor, $plan, $review);
        $this->assertDatabaseCount('bulk_email_logs', 2); Bus::assertDispatchedTimes(SendCampInvitation::class, 2);
        $this->actingAs($this->actor())->post(route('backend.camps.mail-queue', [$plan, $review]), ['confirmed' => 1])->assertForbidden();
        $this->assertSame(0, (new SendCampInvitation(1, $plan->event_id))->tries);
        $other = $this->child(['name' => 'Second', 'email' => 'second@example.test', 'email_2' => null]);
        $n2 = app(CampWorkflow::class)->approve($actor, $plan, $other->id, $division->id, 'Reviewed');
        $stale = $service->review($actor, $plan, [$n2->id], 'Camp invitation', 'Please join us.');
        DB::table('players')->where('id', $other->id)->update(['email' => 'changed@example.test']);
        $this->actingAs($actor)->post(route('backend.camps.mail-queue', [$plan, $stale]), ['confirmed' => 1])->assertConflict();
        $this->assertDatabaseCount('bulk_email_logs', 2); Mail::assertNothingSent();
        $this->get(route('backend.camps.show', $plan))->assertOk()->assertSee('queued');
    }

    public function test_camp_capacity_counts_pending_holds_paid_entries_and_moves_once_per_beneficiary_and_scopes_withdrawals(): void
    {
        [$actor, $plan, $division] = $this->configured('open'); $player = $this->child();
        $registration = DB::table('registrations')->insertGetId([]);
        DB::table('player_registrations')->insert(['player_id' => $player->id, 'registration_id' => $registration]);
        $order = DB::table('registration_orders')->insertGetId(['user_id' => $actor->id, 'pay_status' => 0, 'payfast_paid' => false]);
        DB::table('registration_order_items')->insert(['order_id' => $order, 'registration_id' => $registration, 'player_id' => $player->id, 'category_event_id' => $division->id]);
        $foreign = CategoryEvent::create(['event_id' => Event::factory()->create()->id]);
        DB::table('category_event_registrations')->insert(['category_event_id' => $foreign->id, 'registration_id' => $registration, 'status' => 'withdrawn', 'withdrawn_at' => now()]);
        $admission = app(CampAdmission::class); $this->assertSame([$player->id], $admission->occupied($plan->event_id));
        $entry = DB::table('category_event_registrations')->insertGetId(['category_event_id' => $division->id, 'registration_id' => $registration, 'payment_status_id' => 1, 'status' => 'active']);
        DB::table('category_adjustments')->insert(['entry_id' => $entry, 'event_id' => $plan->event_id, 'player_id' => $player->id, 'target_category_id' => $division->id, 'status' => 'pending']);
        $this->assertSame([$player->id], $admission->occupied($plan->event_id));
        CampConfiguration::where('event_id', $plan->event_id)->update(['capacity' => 1]);
        DB::transaction(function () use ($admission, $plan, $division, $player) { $admission->lockEvents([$plan->event_id]); $admission->assertAvailable($division, $player->id); });
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        DB::transaction(fn () => $admission->assertAvailable($division, $this->child(['name' => 'Other'])->id));
    }

    public function test_decline_http_route_records_actor_and_cannot_override_paid_entry_or_pending_checkout(): void
    {
        [$actor, $plan, $division] = $this->configured(); $player = $this->child();
        $nominee = app(CampWorkflow::class)->approve($actor, $plan, $player->id, $division->id, 'Reviewed');
        $url = route('camp.invitation.decline', $nominee->token_encrypted);
        $sponsor = User::factory()->create();
        $this->actingAs($sponsor)->post($url, ['confirmed' => 1])->assertRedirect();
        $this->assertDatabaseHas('camp_nominees', ['id' => $nominee->id, 'status' => 'declined', 'declined_by' => $sponsor->id]);
        $this->assertDatabaseCount('user_players', 0);
        foreach (['paid', 'pending', 'move'] as $state) {
            $p = $this->child(['name' => ucfirst($state)]);
            $n = app(CampWorkflow::class)->approve($actor, $plan, $p->id, $division->id, 'Reviewed');
            $registration = DB::table('registrations')->insertGetId([]);
            DB::table('player_registrations')->insert(['player_id' => $p->id, 'registration_id' => $registration]);
            if ($state === 'paid') {
                DB::table('category_event_registrations')->insert(['registration_id' => $registration, 'category_event_id' => $division->id, 'payment_status_id' => 1, 'status' => 'active']);
                $later = DB::table('registrations')->insertGetId([]);
                DB::table('player_registrations')->insert(['player_id' => $p->id, 'registration_id' => $later]);
                DB::table('category_event_registrations')->insert(['registration_id' => $later, 'category_event_id' => $division->id, 'payment_status_id' => 1, 'status' => 'withdrawn', 'withdrawn_at' => now()]);
            } elseif ($state === 'pending') {
                $order = DB::table('registration_orders')->insertGetId(['user_id' => $actor->id, 'pay_status' => 0, 'payfast_paid' => false]);
                DB::table('registration_order_items')->insert(['order_id' => $order, 'registration_id' => $registration, 'player_id' => $p->id, 'category_event_id' => $division->id]);
            } else {
                DB::table('category_adjustments')->insert(['entry_id' => 9999, 'event_id' => $plan->event_id, 'player_id' => $p->id, 'target_category_id' => $division->id, 'status' => 'pending']);
            }
            $this->post(route('camp.invitation.decline', $n->token_encrypted), ['confirmed' => 1])->assertUnprocessable();
            $this->assertSame('approved', $n->fresh()->status);
        }
    }

    public function test_configured_deadline_stops_new_mail_review_and_skips_queued_worker_before_transport(): void
    {
        [$actor, $plan, $division] = $this->configured(); $player = $this->child();
        $nominee = app(CampWorkflow::class)->approve($actor, $plan, $player->id, $division->id, 'Reviewed');
        $mail = app(CampInvitations::class);
        $review = $mail->review($actor, $plan, [$nominee->id], 'Invitation', 'Join us.');
        $mail->queue($actor, $plan, $review);
        $this->mock(\App\Services\EventMailLogService::class)->shouldReceive('recordIssue')->zeroOrMoreTimes();
        CampConfiguration::where('event_id', $plan->event_id)->update(['registration_closes_at' => now()->subMinute()]);
        foreach (BulkEmailLog::all() as $log) {
            (new SendCampInvitation($log->id, $plan->event_id))->handle();
            $this->assertSame('skipped', $log->fresh()->status);
        }
        Mail::assertNothingSent();
        try { $mail->review($actor, $plan, [$nominee->id], 'Invitation', 'Join us.'); $this->fail('Closed camp prepared mail'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('registration', $e->getMessage()); }
    }

    public function test_worker_skips_stale_contact_or_revoked_nominee_without_transport(): void
    {
        [$actor, $plan, $division] = $this->configured(); $player = $this->child();
        $n = app(CampWorkflow::class)->approve($actor, $plan, $player->id, $division->id, 'Reviewed');
        $mail = app(CampInvitations::class); $review = $mail->review($actor, $plan, [$n->id], 'Invitation', 'Join us.'); $mail->queue($actor, $plan, $review);
        $this->mock(\App\Services\EventMailLogService::class)->shouldReceive('recordIssue')->zeroOrMoreTimes();
        DB::table('players')->where('id', $player->id)->update(['email' => 'new@example.test']);
        $logs = BulkEmailLog::orderBy('id')->get();
        (new SendCampInvitation($logs[0]->id, $plan->event_id))->handle();
        $this->assertSame('skipped', $logs[0]->fresh()->status);
        $n->update(['status' => 'revoked']);
        (new SendCampInvitation($logs[1]->id, $plan->event_id))->handle();
        $this->assertSame('skipped', $logs[1]->fresh()->status); Mail::assertNothingSent();
    }

    public function test_fresh_review_can_retry_a_proven_skipped_attempt_but_never_uncertain_or_sent_attempts(): void
    {
        [$actor, $plan, $division] = $this->configured(); $player = $this->child(['email_2' => null]);
        $n = app(CampWorkflow::class)->approve($actor, $plan, $player->id, $division->id, 'Reviewed');
        $mail = app(CampInvitations::class);
        $review = $mail->review($actor, $plan, [$n->id], 'Invitation', 'Join us.'); $mail->queue($actor, $plan, $review);
        $this->mock(\App\Services\EventMailLogService::class)->shouldReceive('recordIssue')->zeroOrMoreTimes();
        $log = BulkEmailLog::first(); $log->markAsSkipped('Known pre-transport stale state.');
        $fresh = $mail->review($actor, $plan, [$n->id], 'Invitation', 'Join us.'); $mail->queue($actor, $plan, $fresh);
        $this->assertDatabaseCount('bulk_email_logs', 2);
        DB::table('bulk_email_logs')->where('id', '!=', $log->id)->update(['status' => 'acceptance_unknown']);
        $third = $mail->review($actor, $plan, [$n->id], 'Invitation', 'Join us.'); $mail->queue($actor, $plan, $third);
        $this->assertDatabaseCount('bulk_email_logs', 2); Mail::assertNothingSent();
    }
}
