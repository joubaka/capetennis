<?php

namespace Tests\Feature\Draw;

use App\Jobs\SendMatchResultNotification;
use App\Mail\MatchResultMail;
use App\Models\{CategoryEvent, Draw, Event, Fixture, MatchResultNotification, Player, Registration, TeamFixture, TeamFixturePlayer, User};
use App\Services\{MailAccountManager, MatchResultNotificationService, TeamFixtureScoreService};
use Illuminate\Support\Facades\{DB, Mail};
use Illuminate\Foundation\Testing\{DatabaseTruncation, RefreshDatabaseState};
use Tests\TestCase;

class MatchResultNotificationTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        if (config('database.default') === 'sqlite' && config('database.connections.sqlite.database') === ':memory:') {
            RefreshDatabaseState::$migrated = false;
            $this->beforeApplicationDestroyed(function (): void {
                RefreshDatabaseState::$migrated = false;
                RefreshDatabaseState::$inMemoryConnections = [];
            });
        }
    }

    private function fixture(): TeamFixture
    {
        $event = Event::factory()->create(['published' => true, 'result_notifications_enabled' => true]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'locked' => false]);
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'numSets' => 3, 'match_nr' => 1, 'round_nr' => 1]);
        $players = Player::factory()->count(2)->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $teams = \App\Models\Team::factory()->count(2)->create(['category_event_id' => $category->id]);
        foreach ($players as $index => $player) \App\Models\TeamPlayer::create(['team_id' => $teams[$index]->id, 'player_id' => $player->id, 'rank' => 1]);
        TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 1, 'team1_id' => $players[0]->id, 'team2_id' => $players[1]->id]);
        $parent = User::factory()->create(['email' => 'parent@example.test']);
        $players[0]->users()->attach($parent);
        $admin = User::factory()->create(['email' => 'event-admin@example.test']);
        $event->admins()->attach($admin);
        return $fixture->fresh();
    }

    private function save(TeamFixture $fixture, bool $reverse = false): void
    {
        app(TeamFixtureScoreService::class)->save($fixture, ['set1_home' => $reverse ? 2 : 6, 'set1_away' => $reverse ? 6 : 2,
            'set2_home' => $reverse ? 3 : 6, 'set2_away' => $reverse ? 6 : 3]);
    }

    public function test_completed_result_queues_each_contact_once_and_corrections_have_new_revisions(): void
    {
        $fixture = $this->fixture();
        $this->save($fixture);
        $this->assertDatabaseCount('match_result_notifications', 3);
        $this->assertDatabaseCount('jobs', 3);
        $this->save($fixture);
        $this->assertDatabaseCount('match_result_notifications', 3);
        $this->save($fixture, true);
        $this->assertDatabaseCount('match_result_notifications', 6);
        $notification = MatchResultNotification::latest('id')->first();
        $this->assertSame(2, $notification->revision);
        $mail = new MatchResultMail($notification);
        $this->assertStringContainsString('Corrected', $mail->subjectLine());
        $this->assertStringContainsString('2–6, 3–6', $mail->body(['event-admin@example.test']));
        $this->assertSame(['event-admin@example.test'], app(MatchResultNotificationService::class)->replyTo($notification));
        $this->save($fixture);
        $this->assertSame(3, MatchResultNotification::latest('id')->first()->revision);
        $this->assertDatabaseCount('match_result_notifications', 9);
    }

    public function test_result_emails_wait_thirty_minutes_and_corrections_restart_the_wait(): void
    {
        $this->freezeTime();
        $fixture = $this->fixture();
        $this->save($fixture);
        $originalAvailableAt = now()->addMinutes(30)->timestamp;
        $this->assertSame([$originalAvailableAt], DB::table('jobs')->distinct()->pluck('available_at')->map(fn ($time) => (int) $time)->all());
        $old = MatchResultNotification::first();

        $this->travel(10)->minutes();
        $this->save($fixture, true);
        $correctedAvailableAt = now()->addMinutes(30)->timestamp;
        $this->assertSame([$correctedAvailableAt], DB::table('jobs')->orderByDesc('id')->limit(3)->pluck('available_at')->map(fn ($time) => (int) $time)->unique()->values()->all());
        $this->assertDatabaseCount('jobs', 6);

        $this->travel(20)->minutes();
        Mail::fake();
        (new SendMatchResultNotification($old->id))->handle(app(MatchResultNotificationService::class));
        $this->assertSame('superseded', $old->fresh()->status);
        Mail::assertNothingSent();
        $this->assertSame(3, DB::table('jobs')->where('available_at', '>', now()->timestamp)->count());

        $this->travel(10)->minutes();
        $this->mock(MailAccountManager::class)->shouldReceive('getMailer')->once()->andReturn('array');
        $corrected = MatchResultNotification::where('revision', 2)->where('recipient', 'parent@example.test')->firstOrFail();
        (new SendMatchResultNotification($corrected->id))->handle(app(MatchResultNotificationService::class));
        Mail::assertSent(MatchResultMail::class, fn ($mail) => $mail->resultNotification->id === $corrected->id
            && str_contains($mail->body([]), '2–6, 3–6'));
        Mail::assertSent(MatchResultMail::class, 1);
    }

    public function test_all_linked_parent_contacts_receive_a_personal_footer_and_player_contacts_do_not(): void
    {
        $fixture = $this->fixture();
        $player = $fixture->fixturePlayers->first()->player1;
        $player->update(['email' => 'player@example.test']);
        $secondParent = User::factory()->create(['email' => 'second-parent@example.test']);
        $player->users()->attach($secondParent);
        $player->update(['userId' => $secondParent->id]);
        $player->users()->attach(User::factory()->create(['email' => 'PLAYER@example.test']));
        $this->save($fixture);

        foreach (['player@example.test', 'parent@example.test', 'second-parent@example.test'] as $email) {
            $this->assertSame(1, MatchResultNotification::where('recipient', $email)->count());
        }
        $this->assertDatabaseCount('match_result_notifications', 4);
        $parentMail = new MatchResultMail(MatchResultNotification::where('recipient', 'second-parent@example.test')->firstOrFail());
        $html = $parentMail->body([]);
        $this->assertStringContainsString('as a parent', $html);
        $this->assertStringContainsString(e($player->full_name), $html);
        $this->assertStringContainsString(route('backend.dashboard', ['manage_players' => 1]).'#dashboard-account', $html);
        $playerMail = new MatchResultMail(MatchResultNotification::where('recipient', 'player@example.test')->firstOrFail());
        $this->assertStringNotContainsString('as a parent', $playerMail->body([]));
        $notification = $parentMail->resultNotification;
        $this->actingAs($secondParent)->deleteJson(route('backend.user.players.destroy', [$secondParent, $player]))->assertOk();
        $this->assertSame(0, (int) $player->fresh()->userId);
        $this->assertFalse($player->fresh()->users->contains('id', $secondParent->id));
        $this->assertFalse(app(MatchResultNotificationService::class)->current($notification));
        $this->assertStringNotContainsString('as a parent', $parentMail->body([]));
    }

    public function test_parent_footer_profile_destination_lists_legacy_links_and_opens_admin_account(): void
    {
        $parent = User::factory()->create();
        $event = Event::factory()->create();
        $event->admins()->attach($parent);
        $legacy = Player::factory()->create(['userId' => $parent->id, 'name' => 'LegacyParentPlayer']);
        $unrelated = Player::factory()->create(['name' => 'UnrelatedParentPlayer']);

        $this->actingAs($parent)->get(route('backend.dashboard', ['manage_players' => 1]))
            ->assertOk()->assertSee('id="my-account"  open', false)
            ->assertSee('LegacyParentPlayer')->assertDontSee('UnrelatedParentPlayer')
            ->assertViewHas('user', fn ($user) => $user->players->pluck('id')->all() === [$legacy->id]);
        $this->deleteJson(route('backend.user.players.destroy', [$parent, $legacy]))->assertOk();
        $this->assertSame(0, (int) $legacy->fresh()->userId);
        $this->assertDatabaseHas('players', ['id' => $unrelated->id]);
    }

    public function test_rollback_creates_neither_notification_nor_job(): void
    {
        $fixture = $this->fixture();
        DB::beginTransaction();
        $this->save($fixture);
        $this->assertDatabaseCount('match_result_notifications', 3);
        $this->assertDatabaseCount('jobs', 0);
        DB::rollBack();
        $this->assertDatabaseCount('match_result_notifications', 0);
        $this->assertDatabaseCount('team_fixture_results', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_partial_and_unpublished_results_never_queue(): void
    {
        $fixture = $this->fixture();
        app(TeamFixtureScoreService::class)->save($fixture, ['set1_home' => 6, 'set1_away' => 2]);
        $this->assertDatabaseCount('match_result_notifications', 0);
        $fixture->draw->update(['published' => false]);
        $this->save($fixture);
        $this->assertDatabaseCount('match_result_notifications', 0);
        $fixture->draw->update(['published' => true, 'result_notifications_enabled' => true]);
        $fixture->draw->event->update(['published' => false]);
        $this->save($fixture);
        $this->assertDatabaseCount('match_result_notifications', 0);
    }

    public function test_stale_deleted_and_unlinked_recipient_jobs_are_suppressed(): void
    {
        $fixture = $this->fixture();
        $this->save($fixture);
        $old = MatchResultNotification::first();
        $this->save($fixture, true);
        Mail::fake();
        (new SendMatchResultNotification($old->id))->handle(app(MatchResultNotificationService::class));
        $this->assertSame('superseded', $old->fresh()->status);
        $parent = MatchResultNotification::where('revision', 2)->where('recipient', 'parent@example.test')->first();
        DB::table('user_players')->where('user_id', User::where('email', 'parent@example.test')->value('id'))->delete();
        (new SendMatchResultNotification($parent->id))->handle(app(MatchResultNotificationService::class));
        $this->assertSame('superseded', $parent->fresh()->status);
        app(TeamFixtureScoreService::class)->delete($fixture);
        $latest = MatchResultNotification::where('revision', 2)->where('status', 'pending')->first();
        (new SendMatchResultNotification($latest->id))->handle(app(MatchResultNotificationService::class));
        $this->assertSame('superseded', $latest->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_send_claim_is_idempotent_and_uses_only_match_contacts(): void
    {
        $fixture = $this->fixture();
        $outsider = Player::factory()->create();
        $this->save($fixture);
        $this->assertFalse(MatchResultNotification::where('recipient', $outsider->email)->exists());
        Mail::fake();
        $this->mock(MailAccountManager::class)->shouldReceive('getMailer')->once()->andReturn('array');
        $notification = MatchResultNotification::first();
        $job = new SendMatchResultNotification($notification->id);
        $job->handle(app(MatchResultNotificationService::class));
        $job->handle(app(MatchResultNotificationService::class));
        Mail::assertSent(MatchResultMail::class, 1);
        $this->assertSame('sent', $notification->fresh()->status);
        $fixture->draw->update(['event_id' => Event::factory()->create()->id]);
        $this->assertFalse(app(MatchResultNotificationService::class)->current($notification));
    }

    public function test_individual_winner_uses_all_sets_and_queues_only_event_participants(): void
    {
        $event = Event::factory()->create(['published' => true, 'result_notifications_enabled' => true]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $registrations = Registration::factory()->count(2)->create();
        foreach ($registrations as $registration) {
            $registration->players()->attach(Player::factory()->create());
            $registration->categoryEvents()->attach($category);
        }
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $registrations[0]->id, 'registration2_id' => $registrations[1]->id]);
        foreach ([[6, 2], [6, 3], [2, 6]] as $index => $set) {
            $fixture->fixtureResults()->create(['set_nr' => $index + 1, 'registration1_score' => $set[0], 'registration2_score' => $set[1]]);
        }
        $fixture->update(['winner_registration' => $registrations[0]->id, 'match_status' => 1]);
        app(MatchResultNotificationService::class)->record($fixture);
        $this->assertSame($registrations[0]->id, $fixture->fresh()->winner_id);
        $group = \App\Models\DrawGroup::create(['draw_id' => $draw->id, 'name' => 'A']);
        foreach ($registrations as $registration) $group->groupRegistrations()->create(['registration_id' => $registration->id]);
        $fixture->update(['stage' => 'RR', 'draw_group_id' => $group->id]);
        $rows = app(\App\Domain\Draws\Services\StandingsService::class)->forDraw($draw->fresh())[$group->id];
        $this->assertSame($registrations[0]->id, $rows[0]['reg_id']);
        $this->assertSame(1, $rows[0]['wins']);
        $this->assertSame(2, $rows[0]['sets_won']);
        $this->assertSame(14, $rows[0]['games_won']);
        $fixture->update(['winner_registration' => null]);
        $this->assertSame($registrations[0]->id, $fixture->fresh()->winner_id);
        $rows = app(\App\Domain\Draws\Services\StandingsService::class)->forDraw($draw->fresh())[$group->id];
        $this->assertSame(1, $rows[0]['wins']);
        $this->assertDatabaseCount('match_result_notifications', 2);
        $fixture->update(['match_status' => 1]);
        $this->assertDatabaseCount('match_result_notifications', 2);
        $registrations[1]->categoryEvents()->detach($category);
        $this->assertFalse(app(MatchResultNotificationService::class)->current(MatchResultNotification::first()));
    }

    public function test_real_array_transport_passes_narrow_review_guard_and_tampering_is_rejected(): void
    {
        $fixture = $this->fixture();
        $this->save($fixture);
        $this->mock(MailAccountManager::class)->shouldReceive('getMailer')->once()->andReturn('array');
        $notification = MatchResultNotification::first();
        (new SendMatchResultNotification($notification->id))->handle(app(MatchResultNotificationService::class));
        $this->assertSame('sent', $notification->fresh()->status);
        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());

        $pending = MatchResultNotification::where('status', 'pending')->first();
        $pending->update(['status' => 'sending', 'claimed_at' => now()]);
        $mail = (new MatchResultMail($pending))->build();
        $service = app(MatchResultNotificationService::class);
        $message = (new \Symfony\Component\Mime\Email())->to('outsider@example.test')
            ->subject($mail->subjectLine())->html($mail->body($service->replyTo($pending)));
        foreach ($service->replyTo($pending) as $email) $message->addReplyTo($email);
        try {
            app(\App\Listeners\RequireEventEmailReview::class)->handle(new \Illuminate\Mail\Events\MessageSending($message, ['resultNotification' => $pending]));
            $this->fail('An altered recipient must be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $message->to($pending->recipient)->text('Unapproved alternative content');
        try {
            app(\App\Listeners\RequireEventEmailReview::class)->handle(new \Illuminate\Mail\Events\MessageSending($message, ['resultNotification' => $pending]));
            $this->fail('An unapproved plaintext alternative must be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $message->text(null)->attach('Unapproved attachment', 'private.txt');
        try {
            app(\App\Listeners\RequireEventEmailReview::class)->handle(new \Illuminate\Mail\Events\MessageSending($message, ['resultNotification' => $pending]));
            $this->fail('An unapproved attachment must be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_imported_contacts_replace_stale_address_after_profile_linking(): void
    {
        $fixture = $this->fixture();
        $category = CategoryEvent::factory()->create(['event_id' => $fixture->draw->event_id]);
        $team = \App\Models\Team::factory()->create(['category_event_id' => $category->id]);
        $imported = \App\Models\NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Imported', 'surname' => 'Player', 'rank' => 1, 'pay_status' => 0, 'email' => 'imported@example.test']);
        $slot = $fixture->fixturePlayers->first();
        $slot->update(['team1_id' => null, 'team1_no_profile_id' => $imported->id]);
        $this->save($fixture);
        $this->assertTrue(MatchResultNotification::where('recipient', 'imported@example.test')->exists());
        $profile = Player::factory()->create(['email' => 'linked@example.test']);
        $imported->update(['player_profile' => $profile->id]);
        $parent = User::factory()->create(['email' => 'imported-parent@example.test']);
        $profile->users()->attach($parent);
        $notification = MatchResultNotification::where('recipient', 'imported@example.test')->first();
        $this->assertFalse(app(MatchResultNotificationService::class)->current($notification));
        $this->assertContains('linked@example.test', app(MatchResultNotificationService::class)->recipients([], [$imported->id]));
        $this->assertNotContains('imported@example.test', app(MatchResultNotificationService::class)->recipients([], [$imported->id]));
        $this->assertContains('imported-parent@example.test', app(MatchResultNotificationService::class)->recipients([], [$imported->id]));
        $parentNotification = new MatchResultNotification(['recipient' => $parent->email, 'snapshot' => $notification->snapshot]);
        $this->assertSame([$profile->full_name], app(MatchResultNotificationService::class)->linkedParentNames($parentNotification));
    }

    public function test_canonical_tie_publication_and_foreign_participants_are_checked(): void
    {
        $fixture = $this->fixture();
        $category = CategoryEvent::factory()->create(['event_id' => $fixture->draw->event_id]);
        $teams = \App\Models\Team::factory()->count(2)->create(['category_event_id' => $category->id]);
        $tie = \App\Models\TeamTie::create(['draw_id' => $fixture->draw_id, 'round_nr' => 1, 'tie_nr' => 1,
            'home_team_id' => $teams[0]->id, 'away_team_id' => $teams[1]->id, 'status' => 'published', 'published_at' => now()]);
        $fixture->update(['team_tie_id' => $tie->id]);
        $slot = $fixture->fixturePlayers->first();
        foreach ([1, 2] as $side) \App\Models\TeamPlayer::create(['team_id' => $teams[$side - 1]->id, 'player_id' => $slot->{'team'.$side.'_id'}, 'rank' => 1]);
        $this->save($fixture->fresh());
        $this->assertDatabaseCount('match_result_notifications', 3);
        $notification = MatchResultNotification::first();
        $tie->update(['published_at' => null]);
        $this->assertFalse(app(MatchResultNotificationService::class)->current($notification));
        $tie->update(['published_at' => now()]);
        $slot->update(['team1_id' => Player::factory()->create()->id]);
        $this->assertNull(app(MatchResultNotificationService::class)->snapshot($fixture->fresh()));
        $this->assertFalse(app(MatchResultNotificationService::class)->current($notification));
    }

    public function test_individual_partial_result_has_no_match_win_or_notification(): void
    {
        $fixture = Fixture::factory()->create(['draw_id' => Draw::factory()->create(['event_id' => Event::factory()->create(['published' => true, 'result_notifications_enabled' => true])->id, 'published' => true])->id]);
        $fixture->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2]);
        $fixture->update(['winner_registration' => 123]);
        $this->assertNull($fixture->fresh()->winner_id);
        $this->assertDatabaseCount('match_result_notifications', 0);
    }

    public function test_deleted_result_reentered_with_same_score_creates_a_new_revision(): void
    {
        $fixture = $this->fixture();
        $this->save($fixture);
        $old = MatchResultNotification::first();
        app(TeamFixtureScoreService::class)->delete($fixture);
        $this->save($fixture);
        $this->assertNotNull($old->fresh()->invalidated_at);
        $this->assertFalse(app(MatchResultNotificationService::class)->current($old->fresh()));
        $this->assertSame(2, MatchResultNotification::latest('id')->first()->revision);
        $this->assertDatabaseCount('match_result_notifications', 6);
    }

    public function test_administrative_save_of_historical_result_does_not_email_but_score_correction_does(): void
    {
        $event = Event::factory()->create(['published' => true, 'result_notifications_enabled' => true]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $registrations = Registration::factory()->count(2)->create();
        foreach ($registrations as $registration) {
            $registration->players()->attach(Player::factory()->create());
            $registration->categoryEvents()->attach($category);
        }
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $registrations[0]->id, 'registration2_id' => $registrations[1]->id]);
        foreach ([[6, 2], [6, 3]] as $index => $set) {
            $fixture->fixtureResults()->create(['set_nr' => $index + 1, 'registration1_score' => $set[0], 'registration2_score' => $set[1]]);
        }
        $fixture->update(['winner_registration' => $registrations[0]->id, 'match_status' => 1]);
        $fixture->update(['scheduled' => true]);
        $this->assertDatabaseCount('match_result_notifications', 0);
        $this->assertDatabaseCount('jobs', 0);
        app(\App\Services\DrawService::class)->saveBracketScore($fixture->fresh(), [[6, 1], [6, 2]]);
        $this->assertDatabaseCount('match_result_notifications', 2);
        $this->assertDatabaseCount('jobs', 2);
        app(\App\Services\DrawService::class)->saveBracketScore($fixture->fresh(), [[6, 0], [6, 1]]);
        $this->assertDatabaseCount('match_result_notifications', 4);
        $this->assertSame(2, MatchResultNotification::latest('id')->first()->revision);
    }

    public function test_event_switch_controls_future_notifications_and_disabling_suppresses_pending(): void
    {
        $fixture = $this->fixture();
        $event = $fixture->draw->event;
        $event->update(['result_notifications_enabled' => false]);
        $this->save($fixture);
        $this->assertDatabaseCount('match_result_notifications', 0);
        $this->assertDatabaseCount('jobs', 0);
        $event->update(['result_notifications_enabled' => true]);
        $this->assertDatabaseCount('match_result_notifications', 0);
        $this->save($fixture, true);
        $this->assertDatabaseCount('match_result_notifications', 3);
        $event->update(['result_notifications_enabled' => false]);
        Mail::fake();
        $notification = MatchResultNotification::first();
        (new SendMatchResultNotification($notification->id))->handle(app(MatchResultNotificationService::class));
        $this->assertSame('superseded', $notification->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_legacy_individual_writers_queue_only_after_complete_first_score(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $event = Event::factory()->create(['published' => true, 'result_notifications_enabled' => true]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $registrations = Registration::factory()->count(2)->create();
        foreach ($registrations as $registration) {
            $registration->players()->attach(Player::factory()->create());
            $registration->categoryEvents()->attach($category);
        }
        foreach (['individual', 'individualNew'] as $index => $type) {
            $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR', 'registration1_id' => $registrations[0]->id, 'registration2_id' => $registrations[1]->id]);
            $scores = $type === 'individual' ? ['set_player1' => [6], 'set_player2' => [2]] : ['sets' => [1 => ['player1' => 6, 'player2' => 2]]];
            $this->postJson(route('draw.insert.result'), ['type' => $type, 'fixture_id' => $fixture->id] + $scores)->assertOk()->assertJsonPath('winner', null);
            $this->assertSame($index * 2, MatchResultNotification::count());
            $scores = $type === 'individual' ? ['set_player1' => [6, 6], 'set_player2' => [2, 3]] : ['sets' => [1 => ['player1' => 6, 'player2' => 2], 2 => ['player1' => 6, 'player2' => 3]]];
            $this->postJson(route('draw.insert.result'), ['type' => $type, 'fixture_id' => $fixture->id] + $scores)->assertOk()->assertJsonPath('winner', $registrations[0]->id);
            $this->assertSame(($index + 1) * 2, MatchResultNotification::count());
        }
    }

    public function test_event_disable_invalidation_survives_reenable_and_stale_event_objects(): void
    {
        $fixture = $this->fixture();
        $this->save($fixture);
        $event = $fixture->draw->event;
        $staleEnabled = clone $event;
        $old = MatchResultNotification::first();
        DB::transaction(function () use ($event): void {
            $event->update(['result_notifications_enabled' => false]);
            app(MatchResultNotificationService::class)->invalidateEvent($event);
        });
        $fixture->draw->setRelation('event', $staleEnabled);
        app(MatchResultNotificationService::class)->record($fixture);
        $this->assertDatabaseCount('match_result_notifications', 3);
        $event->update(['result_notifications_enabled' => true]);
        $this->assertNotNull($old->fresh()->invalidated_at);
        $this->assertFalse(app(MatchResultNotificationService::class)->current($old->fresh()));
        $this->save($fixture, true);
        $this->assertDatabaseCount('match_result_notifications', 6);
        $this->assertSame(2, MatchResultNotification::latest('id')->first()->revision);
    }
}
