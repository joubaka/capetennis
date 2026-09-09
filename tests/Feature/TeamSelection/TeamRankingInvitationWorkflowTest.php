<?php

namespace Tests\Feature\TeamSelection;

use App\Domain\Payments\Services\TeamPaymentService;
use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\BulkEmailLog;
use App\Models\Event;
use App\Models\EventRegion;
use App\Models\Player;
use App\Models\RankingList;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamRegion;
use App\Models\TeamSelectionImport;
use App\Models\TeamSelectionInvitation;
use App\Models\User;
use App\Services\TeamSelection\TeamRankingImportService;
use App\Services\TeamSelection\TeamSelectionInvitationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class TeamRankingInvitationWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_region_import_fills_configured_team_and_creates_two_reserves_from_published_ranking(): void
    {
        [$source, $team, $players] = $this->selectionSource();

        $selectionImport = app(TeamRankingImportService::class)->import($source, User::factory()->create());

        $this->assertSame('published-team-selection-run', $selectionImport->ranking_run_id);
        $this->assertSame(2, $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)->count());
        $this->assertSame(2, $selectionImport->invitations()->where('status', TeamSelectionInvitation::RESERVE)->count());
        $this->assertSame(
            $players->take(2)->pluck('id')->all(),
            TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->orderBy('rank')->pluck('player_id')->all()
        );
        $this->assertSame(
            [1, 2, 3, 4],
            $selectionImport->invitations()->orderBy('queue_position')->pluck('ranking_position')->all()
        );
    }

    public function test_import_never_overwrites_an_existing_team_roster(): void
    {
        [$source, $team] = $this->selectionSource();
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => Player::factory()->create()->id, 'rank' => 1, 'pay_status' => 0]);

        $this->expectException(ValidationException::class);
        app(TeamRankingImportService::class)->import($source, User::factory()->create());
    }

    public function test_confirmed_incomplete_import_keeps_unfilled_team_places_empty(): void
    {
        [$source, $team, $players] = $this->selectionSource();
        $team->update(['num_team_members' => 8]);
        $service = app(TeamRankingImportService::class);
        $preview = $service->preview($source);

        $this->assertNotEmpty($preview['notices']);
        $this->assertEmpty($preview['warnings']);
        try {
            $service->import($source, User::factory()->create());
            $this->fail('Expected an explicit incomplete-roster confirmation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('confirm_incomplete_rosters', $exception->errors());
        }

        $selectionImport = $service->import($source, User::factory()->create(), true);

        $this->assertSame(5, $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)->count());
        $this->assertSame(0, $selectionImport->invitations()->where('status', TeamSelectionInvitation::RESERVE)->count());
        $this->assertSame(8, TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->count());
        $this->assertSame(
            $players->pluck('id')->concat([0, 0, 0])->all(),
            TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->orderBy('rank')->pluck('player_id')->all()
        );
    }

    public function test_another_account_cannot_use_a_players_team_invitation(): void
    {
        [$source] = $this->selectionSource();
        $selectionImport = app(TeamRankingImportService::class)->import($source, User::factory()->create());
        $invitation = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)->firstOrFail();

        $this->expectException(AuthorizationException::class);
        app(TeamSelectionInvitationService::class)->authorizePlayer($invitation->load('player'), User::factory()->create());
    }

    public function test_send_accept_and_paid_team_order_keep_invitation_in_sync(): void
    {
        Queue::fake();
        [$source, $team] = $this->selectionSource();
        $actor = User::factory()->create();
        $selectionImport = app(TeamRankingImportService::class)->import($source, $actor);
        $stats = app(TeamSelectionInvitationService::class)->send($selectionImport, [
            'response_deadline' => now()->addDay(),
            'payment_deadline' => now()->addDays(2),
        ], $actor);
        $this->assertSame(2, $stats['queued']);
        $this->assertSame(0, $stats['missing_email']);

        $invitation = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)->firstOrFail();
        $owner = User::factory()->create();
        $invitation->player->update(['userId' => $owner->id]);
        $accepted = app(TeamSelectionInvitationService::class)->accept($invitation, $owner);
        $this->assertSame(TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, $accepted->status);

        $event = $selectionImport->event;
        $order = app(TeamPaymentService::class)->ensureOrder($owner, $team, $invitation->player, $event, 490.00);
        app(TeamSelectionInvitationService::class)->attachOrder($order);
        $order->update(['pay_status' => true, 'payfast_paid' => true]);
        app(TeamSelectionInvitationService::class)->confirmPaidOrder($order->fresh());

        $invitation->refresh();
        $this->assertSame($order->id, $invitation->order_id);
        $this->assertSame(TeamSelectionInvitation::PAID_CONFIRMED, $invitation->status);
    }

    public function test_declining_a_selected_place_promotes_the_next_reserve_into_the_same_roster_rank(): void
    {
        Queue::fake();
        [$source, $team] = $this->selectionSource();
        $selectionImport = app(TeamRankingImportService::class)->import($source, User::factory()->create());
        app(TeamSelectionInvitationService::class)->send($selectionImport, [
            'response_deadline' => now()->addDay(),
            'payment_deadline' => now()->addDays(2),
        ], User::factory()->create());
        $declining = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)->orderBy('queue_position')->firstOrFail();
        $reserve = $selectionImport->invitations()->where('status', TeamSelectionInvitation::RESERVE)->orderBy('queue_position')->firstOrFail();
        $owner = User::factory()->create();
        $declining->player->update(['userId' => $owner->id]);

        $promoted = app(TeamSelectionInvitationService::class)->decline($declining, $owner, 'Unavailable');

        $this->assertSame($reserve->id, $promoted?->id);
        $this->assertSame(TeamSelectionInvitation::INVITED, $reserve->fresh()->status);
        $this->assertSame(
            $reserve->player_id,
            TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('rank', 1)->value('player_id')
        );
        $this->assertSame(TeamSelectionInvitation::DECLINED, $declining->fresh()->status);
    }

    public function test_team_selection_setup_is_event_scoped_to_an_authorized_admin(): void
    {
        Role::findOrCreate('admin', 'web');
        $teamEventType = DB::table('eventtypes')->insertGetId([
            'name' => 'Team event test', 'type' => 2, 'code' => 'team-selection-test',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $event = Event::factory()->create(['eventType' => $teamEventType]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $region = TeamRegion::create(['region_name' => 'West Coast Primary Schools 2026']);
        DB::table('event_regions')->insert(['event_id' => $event->id, 'region_id' => $region->id, 'ordering' => 1]);

        $this->actingAs($admin)->get(route('backend.team-selection.index', $event))
            ->assertOk()
            ->assertSee('Team Selection &amp; Invitations', false)
            ->assertSee('West Coast Primary Schools 2026');

        $otherAdmin = User::factory()->create()->assignRole('admin');
        $this->actingAs($otherAdmin)->get(route('backend.team-selection.index', $event))->assertForbidden();
    }

    public function test_missing_selected_player_email_blocks_the_whole_send_before_queueing(): void
    {
        Queue::fake();
        [$source] = $this->selectionSource();
        $selectionImport = app(TeamRankingImportService::class)->import($source, User::factory()->create());
        $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)
            ->firstOrFail()->player->update(['email' => null, 'userId' => null]);

        try {
            app(TeamSelectionInvitationService::class)->send($selectionImport, [
                'response_deadline' => now()->addDay(),
                'payment_deadline' => now()->addDays(2),
            ], User::factory()->create());
            $this->fail('Expected the missing email preflight to block sending.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        Queue::assertNothingPushed();
        $this->assertSame('draft', $selectionImport->fresh()->status);
    }

    public function test_send_is_blocked_when_a_reserve_has_no_linked_account(): void
    {
        Queue::fake();
        [$source] = $this->selectionSource();
        $selectionImport = app(TeamRankingImportService::class)->import($source, User::factory()->create());
        $selectionImport->invitations()->where('status', TeamSelectionInvitation::RESERVE)
            ->firstOrFail()->player->update(['userId' => null]);

        $this->expectException(ValidationException::class);
        app(TeamSelectionInvitationService::class)->send($selectionImport, [
            'response_deadline' => now()->addDay(),
            'payment_deadline' => now()->addDays(2),
        ], User::factory()->create());
    }

    public function test_link_rejects_a_series_from_another_event_year(): void
    {
        [$source] = $this->selectionSource();
        $wrongSeries = Series::factory()->create([
            'year' => (int) $source->event->start_date->format('Y') - 1,
        ]);

        $this->expectException(ValidationException::class);
        app(TeamRankingImportService::class)->link(
            $source->event,
            $source->eventRegion,
            $wrongSeries,
            2,
            User::factory()->create()
        );
    }

    public function test_ranking_categories_create_missing_event_categories_and_region_teams_without_duplicates(): void
    {
        [$source, $existingTeam] = $this->selectionSource();
        $secondCategory = Category::factory()->create(['name' => 'u/12 Girls']);
        $secondList = RankingList::factory()->create([
            'series_id' => $source->series_id,
            'category_id' => $secondCategory->id,
        ]);
        foreach (Player::factory()->count(3)->create() as $index => $player) {
            SeriesRanking::create([
                'series_id' => $source->series_id,
                'ranking_list_id' => $secondList->id,
                'category_id' => $secondCategory->id,
                'player_id' => $player->id,
                'rank_position' => $index + 1,
                'total_points' => 800 - $index,
                'meta_json' => ['events_played' => 3],
                'status' => 'published',
                'run_id' => 'published-team-selection-run',
                'published_at' => now(),
            ]);
        }

        $service = app(TeamRankingImportService::class);
        $setup = $service->categorySetup($source);
        $this->assertTrue($setup['published_ready']);
        $this->assertCount(2, $setup['rows']);
        $this->assertSame(3, $setup['rows']->firstWhere('ranking_list_id', $secondList->id)['ranked_count']);

        $payload = $setup['rows']->map(fn (array $row) => [
            'selected' => true,
            'ranking_list_id' => $row['ranking_list_id'],
            'team_name' => $row['team']?->name ?: $row['suggested_team_name'],
            'num_players' => 2,
        ])->all();
        $result = $service->createTeamsFromRankingCategories($source, $payload, User::factory()->create());

        $this->assertSame(['created' => 1, 'linked' => 1, 'unchanged' => 0], $result);
        $this->assertSame(2, CategoryEvent::where('event_id', $source->event_id)->count());
        $this->assertSame(2, Team::withoutGlobalScopes()->where('region_id', $source->region_id)
            ->whereNotNull('category_event_id')->count());
        $this->assertNotNull($existingTeam->fresh()->category_event_id);
        $this->assertSame(2, TeamPlayer::withoutGlobalScopes()->where('team_id', $existingTeam->id)->count());

        $existingTeam->update(['name' => 'Overberg A']);
        $preview = $service->preview($source);
        $this->assertTrue(collect($preview['mappings'])->contains(
            fn (array $mapping) => $mapping['team']->id === $existingTeam->id
                && $mapping['ranking_list']->category_id === $existingTeam->fresh()->category?->category_id
        ));

        $secondResult = $service->createTeamsFromRankingCategories($source, $payload, User::factory()->create());
        $this->assertSame(['created' => 0, 'linked' => 0, 'unchanged' => 2], $secondResult);
        $this->assertSame(2, Team::withoutGlobalScopes()->where('region_id', $source->region_id)
            ->whereNotNull('category_event_id')->count());
    }

    public function test_link_redirect_opens_the_ranking_category_team_setup_popup(): void
    {
        Role::findOrCreate('admin', 'web');
        $teamEventType = DB::table('eventtypes')->insertGetId([
            'name' => 'Team popup test', 'type' => 2, 'code' => 'team-popup-test',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $event = Event::factory()->create(['eventType' => $teamEventType, 'start_date' => now()->addMonth()]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $region = TeamRegion::create(['region_name' => 'Boland Primary Schools']);
        $eventRegion = new EventRegion();
        $eventRegion->event_id = $event->id;
        $eventRegion->region_id = $region->id;
        $eventRegion->ordering = 1;
        $eventRegion->save();
        $series = Series::factory()->create([
            'year' => (int) $event->start_date->format('Y'),
        ]);
        RankingList::factory()->create([
            'series_id' => $series->id,
            'category_id' => Category::factory()->create(['name' => 'u/10 Girls'])->id,
        ]);

        $response = $this->actingAs($admin)->post(route('backend.team-selection.link', [$event, $eventRegion]), [
            'series_id' => $series->id,
            'reserve_count' => 2,
        ]);

        $sourceId = $eventRegion->fresh('rankingSource')->rankingSource->id;
        $response->assertRedirect(route('backend.team-selection.index', $event))
            ->assertSessionHas('open_team_setup_source', $sourceId);
        $this->actingAs($admin)->get(route('backend.team-selection.index', $event))
            ->assertOk()
            ->assertSee('Create teams from ranking categories')
            ->assertSee('Default team size')
            ->assertSee('Apply to checked teams')
            ->assertSee('Team size')
            ->assertSee('u/10 Girls');
    }

    public function test_unlink_preserves_event_categories_and_teams_before_an_import_starts(): void
    {
        [$source, $team] = $this->selectionSource();
        $service = app(TeamRankingImportService::class);
        $setupRow = $service->categorySetup($source)['rows']->first();
        $service->createTeamsFromRankingCategories($source, [[
            'selected' => true,
            'ranking_list_id' => $setupRow['ranking_list_id'],
            'team_name' => $team->name,
            'num_players' => 2,
        ]], User::factory()->create());
        $categoryEventId = $team->fresh()->category_event_id;

        $service->unlink($source, User::factory()->create());

        $this->assertDatabaseMissing('event_region_ranking_sources', ['id' => $source->id]);
        $this->assertDatabaseHas('teams', ['id' => $team->id, 'category_event_id' => $categoryEventId]);
        $this->assertDatabaseHas('category_events', ['id' => $categoryEventId, 'event_id' => $source->event_id]);
    }

    public function test_unlink_is_blocked_after_a_ranked_player_import_exists(): void
    {
        [$source] = $this->selectionSource();
        app(TeamRankingImportService::class)->import($source, User::factory()->create());

        $this->expectException(ValidationException::class);
        app(TeamRankingImportService::class)->unlink($source, User::factory()->create());
    }

    public function test_preview_excludes_historical_teams_that_share_the_region(): void
    {
        [$source, $currentTeam] = $this->selectionSource();
        $historical = new Team();
        $historical->forceFill([
            'user_id' => User::factory()->create()->id,
            'personal_team' => false,
            'name' => $currentTeam->name,
            'region_id' => $currentTeam->region_id,
            'num_team_members' => 2,
            'published' => true,
            'year' => (int) $source->event->start_date->format('Y') - 1,
        ])->save();

        $preview = app(TeamRankingImportService::class)->preview($source);

        $this->assertSame([$currentTeam->id], collect($preview['mappings'])->pluck('team.id')->all());
    }

    public function test_import_is_blocked_when_a_player_occurs_in_more_than_one_team_candidate_list(): void
    {
        [$source, $firstTeam, $players] = $this->selectionSource();
        $category = Category::factory()->create(['name' => 'u/12 Boys']);
        $rankingList = RankingList::factory()->create(['series_id' => $source->series_id, 'category_id' => $category->id]);
        $secondTeam = new Team();
        $secondTeam->forceFill([
            'user_id' => User::factory()->create()->id,
            'personal_team' => false,
            'name' => 'Overberg u/12 Boys',
            'region_id' => $firstTeam->region_id,
            'num_team_members' => 2,
            'published' => true,
            'year' => (int) $source->event->start_date->format('Y'),
        ])->save();
        $candidates = collect([$players->first()])->merge(Player::factory()->count(4)->create());
        foreach ($candidates as $index => $player) {
            SeriesRanking::create([
                'series_id' => $source->series_id,
                'ranking_list_id' => $rankingList->id,
                'category_id' => $category->id,
                'player_id' => $player->id,
                'rank_position' => $index + 1,
                'total_points' => 900 - $index,
                'meta_json' => ['events_played' => 3],
                'status' => 'published',
                'run_id' => 'published-team-selection-run',
                'published_at' => now(),
            ]);
        }

        $preview = app(TeamRankingImportService::class)->preview($source);

        $this->assertTrue(collect($preview['warnings'])->contains(fn ($warning) => str_contains($warning, 'appears in more than one matched team list')));
        $this->expectException(ValidationException::class);
        app(TeamRankingImportService::class)->import($source, User::factory()->create());
    }

    public function test_withdrawal_state_promotes_a_reserve_after_the_roster_slot_is_freed(): void
    {
        Queue::fake();
        [$source, $team] = $this->selectionSource();
        $selectionImport = app(TeamRankingImportService::class)->import($source, User::factory()->create());
        app(TeamSelectionInvitationService::class)->send($selectionImport, [
            'response_deadline' => now()->addDay(),
            'payment_deadline' => now()->addDays(2),
        ], User::factory()->create());
        $withdrawing = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)->orderBy('queue_position')->firstOrFail();
        $reserve = $selectionImport->invitations()->where('status', TeamSelectionInvitation::RESERVE)->orderBy('queue_position')->firstOrFail();
        TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('player_id', $withdrawing->player_id)
            ->update(['player_id' => 0, 'pay_status' => 0]);

        $promoted = app(TeamSelectionInvitationService::class)->markWithdrawn(
            $selectionImport->event_id,
            $team->id,
            $withdrawing->player_id,
            User::factory()->create()
        );

        $this->assertSame(TeamSelectionInvitation::WITHDRAWN, $withdrawing->fresh()->status);
        $this->assertSame($reserve->id, $promoted?->id);
        $this->assertSame($reserve->player_id, TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('rank', 1)->value('player_id'));
    }

    public function test_withdrawal_cancels_an_unpaid_attached_order_before_promoting_a_reserve(): void
    {
        Queue::fake();
        [$source, $team] = $this->selectionSource();
        $selectionImport = app(TeamRankingImportService::class)->import($source, User::factory()->create());
        app(TeamSelectionInvitationService::class)->send($selectionImport, [
            'response_deadline' => now()->addDay(),
            'payment_deadline' => now()->addDays(2),
        ], User::factory()->create());
        $invitation = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)->firstOrFail();
        $owner = User::findOrFail($invitation->player->userId);
        app(TeamSelectionInvitationService::class)->accept($invitation, $owner);
        $order = app(TeamPaymentService::class)->ensureOrder($owner, $team, $invitation->player, $selectionImport->event, 490.00);
        $order->update(['wallet_reserved' => 100.00, 'payfast_amount_due' => 390.00]);
        app(TeamSelectionInvitationService::class)->attachOrder($order);
        TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('player_id', $invitation->player_id)
            ->update(['player_id' => 0, 'pay_status' => 0]);

        app(TeamSelectionInvitationService::class)->markWithdrawn(
            $selectionImport->event_id,
            $team->id,
            $invitation->player_id,
            $owner
        );

        $this->assertSame(0.0, (float) $order->fresh()->wallet_reserved);
        $this->assertSame(0.0, (float) $order->fresh()->payfast_amount_due);
        $this->assertSame(TeamSelectionInvitation::WITHDRAWN, $invitation->fresh()->status);
    }

    public function test_generic_roster_edits_are_blocked_after_a_ranking_import(): void
    {
        [$source, $team] = $this->selectionSource();
        app(TeamRankingImportService::class)->import($source, User::factory()->create());

        $this->expectException(ValidationException::class);
        app(TeamSelectionInvitationService::class)->assertRosterEditable($team);
    }

    public function test_sent_import_deadlines_can_be_extended_and_failed_email_can_be_retried(): void
    {
        Queue::fake();
        [$source] = $this->selectionSource();
        $actor = User::factory()->create();
        $selectionImport = app(TeamRankingImportService::class)->import($source, $actor);
        app(TeamSelectionInvitationService::class)->send($selectionImport, [
            'response_deadline' => now()->addDay(),
            'payment_deadline' => now()->addDays(2),
        ], $actor);
        $oldResponse = $selectionImport->fresh()->response_deadline;
        app(TeamSelectionInvitationService::class)->extendDeadlines($selectionImport, [
            'response_deadline' => now()->addDays(3),
            'payment_deadline' => now()->addDays(4),
        ], $actor);
        $this->assertTrue($selectionImport->fresh()->response_deadline->gt($oldResponse));

        $failed = BulkEmailLog::where('mail_type', 'team_selection_invitation')->firstOrFail();
        $failed->markAsFailed('Temporary transport failure');
        $this->assertSame(1, app(TeamSelectionInvitationService::class)->retryFailedEmails($selectionImport, $actor));
        $this->assertSame('queued', $failed->fresh()->status);
        $this->assertNull($failed->fresh()->failed_at);
    }

    public function test_import_is_blocked_when_a_newer_ranking_run_has_not_been_published(): void
    {
        [$source] = $this->selectionSource();
        $published = SeriesRanking::where('series_id', $source->series_id)->firstOrFail();
        SeriesRanking::create([
            'series_id' => $published->series_id,
            'ranking_list_id' => $published->ranking_list_id,
            'category_id' => $published->category_id,
            'player_id' => Player::factory()->create()->id,
            'rank_position' => 1,
            'total_points' => 1200,
            'meta_json' => ['events_played' => 3],
            'status' => 'calculated',
            'run_id' => 'newer-unpublished-run',
        ]);

        $this->expectException(ValidationException::class);
        app(TeamRankingImportService::class)->preview($source);
    }

    public function test_unsent_import_can_be_restarted_without_touching_other_rosters(): void
    {
        [$source, $team] = $this->selectionSource();
        $manualRegion = TeamRegion::create(['region_name' => 'Imported outside region']);
        $otherTeam = new Team();
        $otherTeam->forceFill(['user_id' => User::factory()->create()->id, 'personal_team' => false, 'name' => 'Manual team', 'region_id' => $manualRegion->id, 'num_team_members' => 1, 'published' => true])->save();
        $manualPlayer = Player::factory()->create();
        TeamPlayer::create(['team_id' => $otherTeam->id, 'player_id' => $manualPlayer->id, 'rank' => 1, 'pay_status' => 0]);
        $selectionImport = app(TeamRankingImportService::class)->import($source, User::factory()->create());

        app(TeamRankingImportService::class)->restartDraft($selectionImport, User::factory()->create());

        $this->assertNull(TeamSelectionImport::find($selectionImport->id));
        $this->assertSame([0, 0], TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->orderBy('rank')->pluck('player_id')->all());
        $this->assertSame($manualPlayer->id, TeamPlayer::withoutGlobalScopes()->where('team_id', $otherTeam->id)->value('player_id'));
    }

    /** @return array{0: \App\Models\EventRegionRankingSource, 1: Team, 2: \Illuminate\Support\Collection<int, Player>} */
    private function selectionSource(): array
    {
        $actor = User::factory()->create();
        $event = Event::factory()->create([
            'published' => true,
            'signUp' => true,
            'status' => 'published',
            'start_date' => now()->addMonth(),
            'deadline' => 1,
        ]);
        $region = TeamRegion::create(['region_name' => 'Overberg Primary Schools 2026']);
        $eventRegion = new EventRegion();
        $eventRegion->event_id = $event->id;
        $eventRegion->region_id = $region->id;
        $eventRegion->ordering = 1;
        $eventRegion->save();
        $eventYear = (int) $event->start_date->format('Y');
        $series = Series::factory()->create(['name' => 'Overberg '.$eventYear, 'year' => $eventYear, 'minimum_events_for_team_selection' => 1]);
        $category = Category::factory()->create(['name' => 'u/10 Boys']);
        $rankingList = RankingList::factory()->create(['series_id' => $series->id, 'category_id' => $category->id]);
        $team = new Team();
        $team->forceFill([
            'user_id' => $actor->id,
            'personal_team' => false,
            'name' => 'Overberg u/10 Boys', 'region_id' => $region->id,
            'num_team_members' => 2, 'published' => true, 'year' => $eventYear,
        ])->save();
        $players = collect();
        foreach (range(1, 5) as $rank) {
            $owner = User::factory()->create(['email' => "ranked{$rank}@example.test"]);
            $player = Player::factory()->create(['email' => "ranked{$rank}@example.test", 'userId' => $owner->id]);
            $players->push($player);
            SeriesRanking::create([
                'series_id' => $series->id,
                'ranking_list_id' => $rankingList->id,
                'category_id' => $category->id,
                'player_id' => $player->id,
                'rank_position' => $rank,
                'total_points' => 1000 - $rank,
                'meta_json' => ['events_played' => 3],
                'status' => 'published',
                'run_id' => 'published-team-selection-run',
                'published_at' => now(),
            ]);
        }

        $source = app(TeamRankingImportService::class)->link($event, $eventRegion, $series, 2, $actor);

        return [$source, $team, $players];
    }
}
