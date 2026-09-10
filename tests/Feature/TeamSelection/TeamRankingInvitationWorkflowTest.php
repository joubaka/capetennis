<?php

namespace Tests\Feature\TeamSelection;

use App\Domain\Payments\Services\TeamPaymentService;
use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\BulkEmailLog;
use App\Models\ClothingItemType;
use App\Models\ClothingSize;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventRegion;
use App\Models\EventRegionManager;
use App\Models\EventRegionRankingSource;
use App\Models\Player;
use App\Models\RankingList;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamRegion;
use App\Models\TeamSelectionImport;
use App\Models\TeamSelectionInvitation;
use App\Models\TeamSelectionRegionAnnouncement;
use App\Models\User;
use App\Services\TeamSelection\TeamRankingImportService;
use App\Services\TeamSelection\TeamSelectionInvitationService;
use App\Services\TeamSelection\RegionManagerAccessService;
use App\Mail\TeamSelectionInvitationMail;
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
            'email_subject' => 'Platteland selection',
            'email_message' => 'Please confirm your availability.',
            'event_information' => 'Arrive at 08:00 at the main venue.',
            'reply_to' => 'teams@example.test',
        ], $actor);
        $this->assertSame(2, $stats['queued']);
        $this->assertSame(0, $stats['missing_email']);

        $invitation = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)->firstOrFail();
        $owner = User::factory()->create();
        $invitation->player->update(['userId' => $owner->id]);
        $accepted = app(TeamSelectionInvitationService::class)->accept($invitation, $owner);
        $this->assertSame(TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, $accepted->status);
        $this->assertNull($accepted->accepted_at);
        $this->assertNotNull($accepted->payment_started_at);
        $this->assertSame($accepted->id, app(TeamSelectionInvitationService::class)->accept($accepted, $owner)->id);

        $event = $selectionImport->event;
        $order = app(TeamPaymentService::class)->ensureOrder($owner, $team, $invitation->player, $event, 490.00);
        app(TeamSelectionInvitationService::class)->attachOrder($order);
        $order->update(['pay_status' => true, 'payfast_paid' => true]);
        app(TeamSelectionInvitationService::class)->confirmPaidOrder($order->fresh());

        $invitation->refresh();
        $this->assertSame($order->id, $invitation->order_id);
        $this->assertSame(TeamSelectionInvitation::PAID_CONFIRMED, $invitation->status);
        $this->assertNotNull($invitation->accepted_at);
        $this->assertSame('Platteland selection', $selectionImport->fresh()->email_subject);
        $this->assertNotNull($selectionImport->fresh()->communication_hash);
        $payload = BulkEmailLog::where('mail_type', 'team_selection_invitation')->firstOrFail()->payload;
        $this->assertSame('Please confirm your availability.', $payload['campaign']['message']);
        $this->assertSame('teams@example.test', $payload['campaign']['reply_to']);
        $html = (new TeamSelectionInvitationMail(
            $invitation->load(['selectionImport.event', 'region', 'team', 'player']),
            'invitation',
            $payload['campaign'],
        ))->render();
        $this->assertStringContainsString('Accept and pay', $html);
        $this->assertStringContainsString('Decline invitation', $html);
        $this->assertStringContainsString('Arrive at 08:00 at the main venue.', $html);
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
        $this->assertSame($owner->id, $declining->fresh()->declined_by_user_id);
        $this->assertSame('authenticated_invitation', $declining->fresh()->decline_method);
    }

    public function test_regional_manager_replacement_promotes_next_reserve_and_records_reason(): void
    {
        Queue::fake();
        [$source, $team] = $this->selectionSource();
        $service = app(TeamSelectionInvitationService::class);
        $selectionImport = app(TeamRankingImportService::class)->import($source, User::factory()->create());
        $service->send($selectionImport, [
            'response_deadline' => now()->addDay(),
            'payment_deadline' => now()->addDays(2),
        ], User::factory()->create());
        $selected = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)
            ->orderBy('queue_position')->firstOrFail();
        $reserve = $selectionImport->invitations()->where('status', TeamSelectionInvitation::RESERVE)
            ->orderBy('queue_position')->firstOrFail();
        $rank = $selected->roster_rank;
        $manager = User::factory()->create();

        $promoted = $service->replaceWithNextReserve($selected, $manager, 'Unavailable for the event weekend');

        $this->assertSame($reserve->id, $promoted->id);
        $this->assertSame(TeamSelectionInvitation::INVITED, $reserve->fresh()->status);
        $this->assertSame($rank, $reserve->fresh()->roster_rank);
        $this->assertSame('regional_manager_replacement', $selected->fresh()->decline_method);
        $this->assertSame($manager->id, $selected->fresh()->declined_by_user_id);
        $this->assertSame('Unavailable for the event weekend', $selected->fresh()->decline_reason);
        $this->assertSame($reserve->player_id, TeamPlayer::withoutGlobalScopes()
            ->where('team_id', $team->id)->where('rank', $rank)->value('player_id'));
    }

    public function test_player_can_decline_after_starting_payment_and_unpaid_order_is_cancelled(): void
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

        app(TeamSelectionInvitationService::class)->decline($invitation->fresh(), $owner, 'School commitment');

        $this->assertSame(TeamSelectionInvitation::DECLINED, $invitation->fresh()->status);
        $this->assertSame($owner->id, $invitation->fresh()->declined_by_user_id);
        $this->assertNull($invitation->fresh()->payment_started_at);
        $this->assertSame(0.0, (float) $order->fresh()->wallet_reserved);
        $this->assertSame(0.0, (float) $order->fresh()->payfast_amount_due);
    }

    public function test_decline_cannot_overtake_a_paid_order_waiting_for_invitation_sync(): void
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
        app(TeamSelectionInvitationService::class)->attachOrder($order);
        $order->update(['pay_status' => true, 'payfast_paid' => true]);

        try {
            app(TeamSelectionInvitationService::class)->decline($invitation->fresh(), $owner, 'Too late');
            $this->fail('Expected paid-order protection to block the decline.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }

        $this->assertSame(TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, $invitation->fresh()->status);
        $this->assertSame($invitation->player_id, TeamPlayer::withoutGlobalScopes()
            ->where('team_id', $team->id)->where('rank', $invitation->roster_rank)->value('player_id'));
    }

    public function test_only_paid_invitation_owner_can_open_enabled_regional_clothing_catalogue(): void
    {
        [$source] = $this->selectionSource();
        $selectionImport = app(TeamRankingImportService::class)->import($source, User::factory()->create());
        $selectionImport->update(['status' => 'sent', 'include_clothing' => true]);
        $invitation = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)->firstOrFail();
        $invitation->update(['status' => TeamSelectionInvitation::PAID_CONFIRMED, 'paid_at' => now(), 'accepted_at' => now()]);
        $region = $source->region;
        $region->update(['clothing_admin' => true, 'clothing_order' => true]);
        $item = ClothingItemType::create([
            'region_id' => $region->id,
            'item_type_name' => 'Regional tracksuit',
            'price' => 850.00,
            'ordering' => 1,
        ]);
        ClothingSize::create(['item_type' => $item->id, 'size' => 'Medium', 'ordering' => 1]);
        $owner = User::findOrFail($invitation->player->userId);

        $this->actingAs($owner)->get(route('team-selection.invitations.clothing', $invitation))
            ->assertOk()
            ->assertSee('Regional tracksuit')
            ->assertSee('R850.00')
            ->assertSee('PayFast fee')
            ->assertSee('Total payable');
        $this->actingAs(User::factory()->create())
            ->get(route('team-selection.invitations.clothing', $invitation))
            ->assertForbidden();
    }

    public function test_event_admin_can_prepare_and_preview_the_actual_regional_invitation_email(): void
    {
        Queue::fake();
        Role::findOrCreate('admin', 'web');
        [$source] = $this->selectionSource();
        $selectionImport = app(TeamRankingImportService::class)->import($source, User::factory()->create());
        $teamType = DB::table('eventtypes')->insertGetId([
            'name' => 'Team invitation preview', 'type' => 2, 'code' => 'team-invitation-preview',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $event = $source->event;
        $event->update([
            'eventType' => $teamType,
            'organizer' => 'Cape Tennis Events Team',
            'email' => 'events@example.test',
            'venue_notes' => 'Players must report 30 minutes before their first match.',
        ]);
        $region = $selectionImport->region;
        $region->update(['clothing_admin' => true, 'clothing_order' => true]);
        $shirt = ClothingItemType::create([
            'region_id' => $region->id,
            'item_type_name' => 'Regional match shirt',
            'price' => 395,
            'ordering' => 1,
        ]);
        ClothingSize::create(['item_type' => $shirt->id, 'size' => '11-12', 'ordering' => 1]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);

        $this->actingAs($admin)->get(route('backend.team-selection.index', $event))
            ->assertOk()
            ->assertSee('Prepare invitations')
            ->assertSee('Preview actual email')
            ->assertSee('Invitation message');

        $payload = [
            'email_subject' => 'Regional Platteland invitation',
            'email_message' => 'A message written by the regional organiser.',
            'event_information' => 'Meet the team manager at 07:30.',
            'reply_to' => 'manager@example.test',
            'response_deadline' => now()->addDay()->format('Y-m-d H:i:s'),
            'payment_deadline' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'replacement_payment_deadline' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'include_clothing' => true,
        ];

        $this->actingAs($admin)->post(route('backend.team-selection.email.preview', [$event, $selectionImport]), $payload)
            ->assertOk()
            ->assertSee('Preview only — no email has been sent')
            ->assertSee('A message written by the regional organiser.')
            ->assertSee('Meet the team manager at 07:30.')
            ->assertSee('Cape Tennis Events Team')
            ->assertSee('Players must report 30 minutes before their first match.')
            ->assertSee('View the published event page')
            ->assertSee('How to accept your invitation')
            ->assertSee('Regional match shirt')
            ->assertSee('R395.00')
            ->assertSee('Sizes: 11-12')
            ->assertSee('How to order:')
            ->assertSee('Accept and pay')
            ->assertSee('Decline invitation');

        $this->assertSame('draft', $selectionImport->fresh()->status);
        $this->assertNull($selectionImport->fresh()->prepared_at);

        $changedPayload = array_replace($payload, ['email_message' => 'This wording changed after preview.']);
        $this->actingAs($admin)->post(route('backend.team-selection.send', [$event, $selectionImport]), $changedPayload)
            ->assertSessionHasErrors('email_preview');
        $this->assertSame('draft', $selectionImport->fresh()->status);
        Queue::assertNothingPushed();

        $this->actingAs($admin)->post(route('backend.team-selection.email.preview', [$event, $selectionImport]), $changedPayload)
            ->assertOk()
            ->assertSee('This wording changed after preview.');
        $this->actingAs($admin)->post(route('backend.team-selection.send', [$event, $selectionImport]), $changedPayload)
            ->assertRedirect();

        $savedImport = $selectionImport->fresh();
        $this->assertSame('sent', $savedImport->status);
        $this->assertSame('Regional match shirt', $savedImport->communication_snapshot['clothing_items'][0]['name']);
        $this->assertEquals(395.0, $savedImport->communication_snapshot['clothing_items'][0]['price']);
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
        $secondRegion = TeamRegion::create(['region_name' => 'Cape Winelands Primary Schools 2026']);
        DB::table('event_regions')->insert(['event_id' => $event->id, 'region_id' => $secondRegion->id, 'ordering' => 2]);

        $this->actingAs($admin)->get(route('backend.team-selection.index', $event))
            ->assertOk()
            ->assertSee('Team Selection &amp; Invitations', false)
            ->assertSee('West Coast Primary Schools 2026')
            ->assertSee('Cape Winelands Primary Schools 2026')
            ->assertSee('data-region-tabs', false)
            ->assertSee('data-bs-toggle="tab"', false);
        $this->actingAs($admin)->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('Administrator')
            ->assertSee(route('admin.events.overview', $event), false)
            ->assertDontSee('Regional administration');

        $otherAdmin = User::factory()->create()->assignRole('admin');
        $this->actingAs($otherAdmin)->get(route('backend.team-selection.index', $event))->assertForbidden();
    }

    public function test_account_without_player_profile_can_be_assigned_and_is_limited_to_its_region(): void
    {
        Role::findOrCreate('admin', 'web');
        $teamType = DB::table('eventtypes')->insertGetId([
            'name' => 'Regional manager test', 'type' => 2, 'code' => 'regional-manager-test',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $event = Event::factory()->create(['eventType' => $teamType]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $manager = User::factory()->create(['email' => 'region.manager@example.test']);
        $firstRegion = TeamRegion::create(['region_name' => 'Assigned Region']);
        $secondRegion = TeamRegion::create(['region_name' => 'Private Other Region']);
        $first = new EventRegion();
        $first->event_id = $event->id; $first->region_id = $firstRegion->id; $first->ordering = 1;
        $first->save();
        $second = new EventRegion();
        $second->event_id = $event->id; $second->region_id = $secondRegion->id; $second->ordering = 2;
        $second->save();

        $this->actingAs($admin)->getJson(route('backend.team-selection.users.search', [$event, 'q' => 'region.manager']))
            ->assertOk()
            ->assertJsonPath('results.0.id', $manager->id)
            ->assertJsonPath('results.0.text', "{$manager->name} · {$manager->email}");
        $this->actingAs($admin)->put(route('backend.team-selection.manager.assign', [$event, $first]), [
            'manager_user_id' => $manager->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('event_region_managers', [
            'event_region_id' => $first->id, 'user_id' => $manager->id,
        ]);
        $this->assertSame([], $manager->ownedPlayerIds());

        $this->actingAs($manager)->get(route('backend.team-selection.index', $event))
            ->assertOk()
            ->assertSee('Assigned Region')
            ->assertSee('for your assigned region')
            ->assertDontSee('Private Other Region')
            ->assertDontSee('data-region-tabs', false);
        $this->actingAs($manager)->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('Regional administration')
            ->assertSee(route('backend.team-selection.index', $event), false)
            ->assertDontSee(route('admin.events.overview', $event), false);
        $this->actingAs($manager)->get(route('admin.events.overview', $event))->assertForbidden();
        $this->actingAs($manager)->getJson(route('backend.team-selection.users.search', [$event, 'q' => 'region']))
            ->assertForbidden();
        $rankingSeries = Series::factory()->create(['year' => (int) $event->start_date->format('Y')]);
        $this->actingAs($manager)->post(route('backend.team-selection.link', [$event, $first]), [
            'series_id' => $rankingSeries->id, 'reserve_count' => 2,
        ])->assertRedirect();
        $this->assertDatabaseHas('event_region_ranking_sources', [
            'event_region_id' => $first->id, 'series_id' => $rankingSeries->id,
        ]);
        $this->actingAs($manager)->post(route('backend.team-selection.link', [$event, $second]), [
            'series_id' => $rankingSeries->id, 'reserve_count' => 2,
        ])->assertForbidden();
        $this->actingAs($manager)->get(route('backend.region.clothing.edit', $firstRegion))->assertOk();
        $this->actingAs($manager)->get(route('backend.region.clothing.edit', $secondRegion))->assertForbidden();
        $this->actingAs($manager)->post(route('backend.team-selection.announcements.store', [$event, $first]), [
            'title' => 'Assigned team update', 'message' => 'Practice starts at 08:00.', 'send_email' => 0,
        ])->assertRedirect();
        $this->assertDatabaseHas('team_selection_region_announcements', [
            'event_region_id' => $first->id, 'title' => 'Assigned team update',
        ]);
        $this->actingAs($manager)->post(route('backend.team-selection.announcements.store', [$event, $second]), [
            'title' => 'Must be blocked', 'message' => 'Wrong region.',
        ])->assertForbidden();
        $this->assertDatabaseCount('team_selection_region_announcements', 1);

        $categoryEvent = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $assignedTeam = new Team();
        $assignedTeam->forceFill(['name' => 'Assigned team', 'region_id' => $firstRegion->id,
            'category_event_id' => $categoryEvent->id, 'num_team_members' => 2, 'published' => false,
            'user_id' => $admin->id, 'personal_team' => false])->save();
        $this->actingAs($manager)->patch(route('backend.team-selection.teams.update', [$event, $first, $assignedTeam]), [
            'name' => 'Managed safely', 'published' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('teams', ['id' => $assignedTeam->id, 'name' => 'Managed safely', 'published' => 1]);
        $this->actingAs($manager)->get(route('backend.team.availablePlayers', ['team_id' => $assignedTeam->id]))
            ->assertForbidden();
    }

    public function test_common_organizer_of_all_ranking_series_events_is_the_default_region_manager(): void
    {
        $teamType = DB::table('eventtypes')->insertGetId([
            'name' => 'Default organizer test', 'type' => 2, 'code' => 'default-organizer-test',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $series = Series::factory()->create();
        $organizer = User::factory()->create();
        foreach (range(1, 3) as $leg) {
            $legEvent = Event::factory()->create(['series_id' => $series->id, 'name' => "Ranking leg {$leg}"]);
            DB::table('event_admins')->insert(['event_id' => $legEvent->id, 'user_id' => $organizer->id]);
        }
        $event = Event::factory()->create(['eventType' => $teamType]);
        $region = TeamRegion::create(['region_name' => 'Default Manager Region']);
        $eventRegion = new EventRegion();
        $eventRegion->event_id = $event->id; $eventRegion->region_id = $region->id; $eventRegion->ordering = 1;
        $eventRegion->save();
        EventRegionRankingSource::create([
            'event_id' => $event->id, 'event_region_id' => $eventRegion->id,
            'region_id' => $region->id, 'series_id' => $series->id, 'reserve_count' => 2,
        ]);

        $access = app(RegionManagerAccessService::class);
        $this->assertSame($organizer->id, $access->defaultManager($eventRegion)->id);
        $this->assertTrue($access->canManage($organizer, $eventRegion));
        $this->actingAs($organizer)->get(route('backend.team-selection.index', $event))
            ->assertOk()->assertSee('Default Manager Region');
    }

    public function test_regional_manager_can_complete_own_region_setup_import_and_restart_without_event_wide_access(): void
    {
        [$source] = $this->selectionSource();
        $teamType = DB::table('eventtypes')->insertGetId([
            'name' => 'Scoped regional workflow', 'type' => 2, 'code' => 'scoped-regional-workflow',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $event = $source->event;
        $event->update(['eventType' => $teamType]);
        $eventRegion = EventRegion::where('event_id', $event->id)->where('region_id', $source->region_id)->firstOrFail();
        $manager = User::factory()->create();
        EventRegionManager::create([
            'event_id' => $event->id,
            'event_region_id' => $eventRegion->id,
            'region_id' => $eventRegion->region_id,
            'user_id' => $manager->id,
            'assigned_by' => User::factory()->create()->id,
        ]);
        $rankingList = RankingList::where('series_id', $source->series_id)->firstOrFail();

        $this->actingAs($manager)->post(route('backend.team-selection.teams.create', [$event, $source]), [
            'categories' => [[
                'selected' => 1,
                'ranking_list_id' => $rankingList->id,
                'team_name' => 'Regional manager team',
                'num_players' => 2,
            ]],
        ])->assertRedirect(route('backend.team-selection.preview', [$event, $source]));

        $this->actingAs($manager)->get(route('backend.team-selection.preview', [$event, $source]))
            ->assertOk()
            ->assertSee('Preview ranked-player import');
        $this->actingAs($manager)->post(route('backend.team-selection.import', [$event, $source]), [
            'confirm_incomplete_rosters' => 1,
        ])
            ->assertRedirect(route('backend.team-selection.index', $event));

        $selectionImport = TeamSelectionImport::where('source_id', $source->id)->firstOrFail();
        $this->actingAs($manager)->post(route('backend.team-selection.restart', [$event, $selectionImport]))
            ->assertRedirect();
        $this->assertDatabaseMissing('team_selection_imports', ['id' => $selectionImport->id]);
        $this->actingAs($manager)->get(route('admin.events.overview', $event))->assertForbidden();
    }

    public function test_multiple_common_series_organizers_require_an_explicit_region_assignment(): void
    {
        $series = Series::factory()->create();
        $organizers = User::factory()->count(2)->create();
        foreach (range(1, 3) as $leg) {
            $legEvent = Event::factory()->create(['series_id' => $series->id]);
            foreach ($organizers as $organizer) {
                DB::table('event_admins')->insert(['event_id' => $legEvent->id, 'user_id' => $organizer->id]);
            }
        }
        $event = Event::factory()->create();
        $region = TeamRegion::create(['region_name' => 'Ambiguous Organizer Region']);
        $eventRegion = new EventRegion();
        $eventRegion->event_id = $event->id; $eventRegion->region_id = $region->id; $eventRegion->ordering = 1;
        $eventRegion->save();
        EventRegionRankingSource::create(['event_id' => $event->id, 'event_region_id' => $eventRegion->id,
            'region_id' => $region->id, 'series_id' => $series->id, 'reserve_count' => 2]);

        $access = app(RegionManagerAccessService::class);
        $this->assertCount(2, $access->defaultManagerCandidates($eventRegion));
        $this->assertNull($access->defaultManager($eventRegion));
        $this->assertFalse($access->canManage($organizers->first(), $eventRegion));
    }

    public function test_only_active_selected_players_can_read_regional_announcements(): void
    {
        [$source] = $this->selectionSource();
        $import = app(TeamRankingImportService::class)->import($source, User::factory()->create());
        $eventRegion = EventRegion::where('event_id', $source->event_id)->where('region_id', $source->region_id)->firstOrFail();
        TeamSelectionRegionAnnouncement::create(['event_id' => $source->event_id, 'event_region_id' => $eventRegion->id,
            'region_id' => $source->region_id, 'created_by' => User::factory()->create()->id,
            'title' => 'Private active-team notice', 'message' => 'Active players only.']);
        $selected = $import->invitations()->where('status', TeamSelectionInvitation::INVITED)->firstOrFail();
        $reserve = $import->invitations()->where('status', TeamSelectionInvitation::RESERVE)->firstOrFail();

        $this->actingAs(User::findOrFail($selected->player->userId))
            ->get(route('team-selection.invitations.show', $selected))->assertOk()->assertSee('Private active-team notice');
        $this->actingAs(User::findOrFail($reserve->player->userId))
            ->get(route('team-selection.invitations.show', $reserve))->assertOk()->assertDontSee('Private active-team notice');
        $selected->update(['status' => TeamSelectionInvitation::DECLINED]);
        $this->actingAs(User::findOrFail($selected->player->userId))
            ->get(route('team-selection.invitations.show', $selected->fresh()))->assertOk()->assertDontSee('Private active-team notice');
    }

    public function test_regional_announcement_email_requires_exact_recipient_confirmation(): void
    {
        Queue::fake();
        Role::findOrCreate('admin', 'web');
        [$source] = $this->selectionSource();
        $teamType = DB::table('eventtypes')->insertGetId(['name' => 'Announcement confirmation', 'type' => 2,
            'code' => 'announcement-confirmation', 'created_at' => now(), 'updated_at' => now()]);
        $event = $source->event;
        $event->update(['eventType' => $teamType]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $import = app(TeamRankingImportService::class)->import($source, $admin);
        app(TeamSelectionInvitationService::class)->send($import, [
            'response_deadline' => now()->addDay(), 'payment_deadline' => now()->addDays(2),
        ], $admin);
        $eventRegion = EventRegion::where('event_id', $event->id)->where('region_id', $source->region_id)->firstOrFail();
        $emails = $import->invitations()->where('status', TeamSelectionInvitation::INVITED)->with('player.user')->get()
            ->pluck('player.user.email')->map(fn ($email) => strtolower($email))->sort()->values();
        $payload = ['title' => 'Confirmed recipients', 'message' => 'Regional update.', 'send_email' => 1,
            'recipient_hash' => hash('sha256', $emails->toJson())];

        $this->actingAs($admin)->post(route('backend.team-selection.announcements.store', [$event, $eventRegion]), $payload)
            ->assertSessionHasErrors('confirm_recipients');
        $this->assertDatabaseMissing('team_selection_region_announcements', ['title' => 'Confirmed recipients']);
        $this->actingAs($admin)->post(route('backend.team-selection.announcements.store', [$event, $eventRegion]), $payload + ['confirm_recipients' => 1])
            ->assertRedirect();
        $announcement = TeamSelectionRegionAnnouncement::where('title', 'Confirmed recipients')->firstOrFail();
        $this->assertSame($emails->count(), BulkEmailLog::where('mail_type', 'event_announcement')
            ->where('related_type', TeamSelectionRegionAnnouncement::class)->where('related_id', $announcement->id)->count());
        $failed = BulkEmailLog::where('mail_type', 'event_announcement')->where('related_type', TeamSelectionRegionAnnouncement::class)
            ->where('related_id', $announcement->id)->firstOrFail();
        $failed->update(['status' => 'failed', 'failed_at' => now()]);
        $this->actingAs($admin)->post(route('backend.team-selection.announcements.retry', [$event, $eventRegion, $announcement]))
            ->assertRedirect();
        $this->assertSame(2, BulkEmailLog::where('mail_type', 'event_announcement')
            ->where('related_type', TeamSelectionRegionAnnouncement::class)->where('related_id', $announcement->id)
            ->where('recipient_email', $failed->recipient_email)->count());
        $this->assertSame(1, BulkEmailLog::where('mail_type', 'event_announcement')
            ->where('related_type', TeamSelectionRegionAnnouncement::class)->where('related_id', $announcement->id)
            ->where('recipient_email', $failed->recipient_email)
            ->where('status', 'queued')->count());
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

    public function test_teams_page_separates_active_roster_and_reserves_without_historical_region_teams(): void
    {
        [$source, $team] = $this->selectionSource();
        $source->event->update(['eventType' => 3]);
        app(TeamRankingImportService::class)->import($source, User::factory()->create());
        $admin = User::factory()->create();
        EventAdmin::create(['event_id' => $source->event_id, 'user_id' => $admin->id]);
        $otherEvent = Event::factory()->create();
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $otherEvent->id]);
        Team::factory()->create([
            'name' => 'Historical region team that must stay hidden',
            'region_id' => $team->region_id,
            'category_event_id' => $otherCategory->id,
        ]);

        $this->actingAs($admin)->get(route('admin.events.teams', $source->event_id))
            ->assertOk()
            ->assertSee($team->name)
            ->assertSee('Reserve queue')
            ->assertSee('Team Selection & Reserves', false)
            ->assertDontSee('Historical region team that must stay hidden');
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

    public function test_expired_response_frees_the_exact_rank_and_promotes_the_next_reserve(): void
    {
        Queue::fake();
        [$source, $team] = $this->selectionSource();
        $actor = User::factory()->create();
        $service = app(TeamSelectionInvitationService::class);
        $selectionImport = app(TeamRankingImportService::class)->import($source, $actor);
        $service->send($selectionImport, [
            'response_deadline' => now()->addDay(),
            'payment_deadline' => now()->addDays(2),
            'replacement_payment_deadline' => now()->addDays(3),
        ], $actor);
        $selected = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)
            ->orderBy('queue_position')->firstOrFail();
        $reserve = $selectionImport->invitations()->where('status', TeamSelectionInvitation::RESERVE)
            ->orderBy('queue_position')->firstOrFail();
        $rank = $selected->roster_rank;
        $selectionImport->update(['response_deadline' => now()->subMinute()]);

        $rows = $service->processExpiredInvitations($selectionImport->event_id, true);

        $this->assertTrue(collect($rows)->contains(fn (array $row) => $row['invitation_id'] === $selected->id));
        $this->assertSame(TeamSelectionInvitation::DECLINED, $selected->fresh()->status);
        $this->assertSame('system_response_deadline', $selected->fresh()->decline_method);
        $this->assertSame(TeamSelectionInvitation::INVITED, $reserve->fresh()->status);
        $this->assertSame($reserve->player_id, TeamPlayer::withoutGlobalScopes()
            ->where('team_id', $team->id)->where('rank', $rank)->value('player_id'));
    }

    public function test_promoted_reserve_uses_replacement_deadline_after_original_response_window(): void
    {
        Queue::fake();
        [$source] = $this->selectionSource();
        $actor = User::factory()->create();
        $service = app(TeamSelectionInvitationService::class);
        $selectionImport = app(TeamRankingImportService::class)->import($source, $actor);
        $service->send($selectionImport, [
            'response_deadline' => now()->addDay(),
            'payment_deadline' => now()->addDays(2),
            'replacement_payment_deadline' => now()->addDays(4),
        ], $actor);
        $selected = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)->firstOrFail();
        $reserve = $service->replaceWithNextReserve($selected, $actor, 'Testing replacement window');
        $selectionImport->update([
            'response_deadline' => now()->subDays(2),
            'payment_deadline' => now()->subDay(),
        ]);
        $owner = User::findOrFail($reserve->player->userId);

        $accepted = $service->accept($reserve->fresh(), $owner);

        $this->assertSame(TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, $accepted->status);
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
