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
use App\Models\Venue;
use App\Services\TeamSelection\TeamRankingImportService;
use App\Services\TeamSelection\TeamSelectionInvitationService;
use App\Services\TeamSelection\RegionManagerAccessService;
use App\Services\Clothing\ClothingPriceService;
use App\Mail\TeamSelectionInvitationMail;
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

    public function test_register_starts_an_invited_team_players_payment_without_a_separate_acceptance(): void
    {
        [$source] = $this->selectionSource();
        $actor = User::factory()->create();
        $selectionImport = app(TeamRankingImportService::class)->import($source, $actor);
        $selectionImport->update([
            'status' => 'prepared',
            'response_deadline' => now()->addDay(),
            'payment_deadline' => now()->addDays(2),
            'replacement_payment_deadline' => now()->addDays(3),
        ]);
        $invitation = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)->firstOrFail();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get(route('team-selection.invitations.show', $invitation))
            ->assertOk()
            ->assertSee('Register and pay')
            ->assertDontSee('Accept and pay');

        $eventRegistrationUrl = route('events.show', [
            'event' => $invitation->event_id,
            'team' => $invitation->team_id,
            'player' => $invitation->player_id,
        ]).'#team-registration-'.$invitation->team_id.'-'.$invitation->player_id;
        $this->actingAs($otherUser)
            ->get(route('team-selection.invitations.show', [$invitation, 'action' => 'pay']))
            ->assertRedirect($eventRegistrationUrl);

        $paymentUrl = route('team.payment.payfast', [$invitation->team_id, $invitation->player_id, $invitation->event_id]);
        $this->actingAs($otherUser)
            ->get($paymentUrl)
            ->assertOk()
            ->assertViewIs('frontend.payfast.team_payment');

        $accepted = $invitation->fresh(['selectionImport.event', 'team', 'player']);

        $this->assertSame(TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, $accepted->status);
        $this->assertNotNull($accepted->payment_started_at);
        $this->assertDatabaseHas('team_payment_orders', [
            'user_id' => $otherUser->id,
            'event_id' => $accepted->event_id,
            'team_id' => $accepted->team_id,
            'player_id' => $accepted->player_id,
        ]);
        $this->assertSame(
            $accepted->player_id,
            app(\App\Domain\Teams\Services\ExternalTeamRosterService::class)
                ->assertCanRegister($otherUser, $accepted->selectionImport->event, $accepted->team, $accepted->player)
                ->player_id
        );
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
        $this->assertStringContainsString('Register and pay', $html);
        $this->assertStringContainsString('View event', $html);
        $this->assertStringContainsString('Decline invitation', $html);
        $this->assertStringContainsString(htmlspecialchars(route('events.show', [
            'event' => $invitation->event_id,
            'team' => $invitation->team_id,
            'player' => $invitation->player_id,
        ]), ENT_QUOTES), $html);
        $this->assertStringContainsString('Decline opens a confirmation window with an optional reason.', $html);
        $this->assertStringNotContainsString('Arrive at 08:00 at the main venue.', $html);
        $this->assertStringNotContainsString('Event information', $html);
        $this->assertStringContainsString('table-layout:fixed', $html);
        $this->assertStringContainsString('word-break:break-word', $html);
    }

    public function test_declining_a_selected_place_closes_the_gap_and_promotes_the_next_reserve_at_the_end(): void
    {
        Queue::fake();
        [$source, $team] = $this->selectionSource();
        $selectionImport = app(TeamRankingImportService::class)->import($source, User::factory()->create());
        app(TeamSelectionInvitationService::class)->send($selectionImport, [
            'response_deadline' => now()->addDay(),
            'payment_deadline' => now()->addDays(2),
        ], User::factory()->create());
        $declining = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)->orderBy('queue_position')->firstOrFail();
        $following = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)
            ->where('id', '!=', $declining->id)->orderBy('roster_rank')->firstOrFail();
        $reserve = $selectionImport->invitations()->where('status', TeamSelectionInvitation::RESERVE)->orderBy('queue_position')->firstOrFail();
        $owner = User::factory()->create();
        $declining->player->update(['userId' => $owner->id]);

        $promoted = app(TeamSelectionInvitationService::class)->decline($declining, $owner, 'Unavailable');

        $this->assertSame($reserve->id, $promoted?->id);
        $this->assertSame(TeamSelectionInvitation::INVITED, $reserve->fresh()->status);
        $this->assertSame(1, $following->fresh()->roster_rank);
        $this->assertSame(2, $reserve->fresh()->roster_rank);
        $this->assertSame(
            $following->player_id,
            TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('rank', 1)->value('player_id')
        );
        $this->assertSame($reserve->player_id,
            TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('rank', 2)->value('player_id'));
        $this->assertSame(TeamSelectionInvitation::DECLINED, $declining->fresh()->status);
        $this->assertSame($owner->id, $declining->fresh()->declined_by_user_id);
        $this->assertSame('authenticated_invitation', $declining->fresh()->decline_method);
    }

    public function test_automatic_replacement_gets_24_hours_when_the_campaign_replacement_deadline_has_passed(): void
    {
        Queue::fake();
        [$source] = $this->selectionSource();
        $selectionImport = app(TeamRankingImportService::class)->import($source, User::factory()->create());
        $selectionImport->update([
            'status' => 'sent',
            'auto_replacement_enabled' => true,
            'response_deadline' => now()->addHours(6),
            'payment_deadline' => now()->addHours(12),
            'replacement_payment_deadline' => now()->subHour(),
        ]);
        $declining = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)
            ->orderBy('queue_position')->firstOrFail();
        $owner = User::findOrFail($declining->player->userId);

        $promoted = app(TeamSelectionInvitationService::class)->decline($declining, $owner, 'Unavailable');
        $promoted = $promoted?->fresh(['selectionImport.event', 'player', 'region', 'team']);

        $this->assertNotNull($promoted);
        $this->assertTrue($promoted->response_deadline_override->between(
            now()->addDay()->subMinute(),
            now()->addDay()->addMinute()
        ));
        $this->assertSame(
            $promoted->response_deadline_override->toDateTimeString(),
            $promoted->payment_deadline_override->toDateTimeString()
        );
        $this->assertSame(
            $promoted->response_deadline_override->toDateTimeString(),
            $promoted->effectiveResponseDeadline()->toDateTimeString()
        );
        $this->view('emails.team-selection.invitation', [
            'invitation' => $promoted,
            'kind' => 'replacement',
            'campaign' => [],
        ])->assertSee($promoted->response_deadline_override->format('d M Y H:i'))
            ->assertSee('24 hours from this invitation');
        Queue::assertPushed(\App\Jobs\SendTeamSelectionInvitationEmailJob::class);
    }

    public function test_manual_replacement_mode_leaves_a_visible_vacancy_until_manager_invites_the_reserve(): void
    {
        Queue::fake();
        [$source] = $this->selectionSource();
        $manager = User::factory()->create();
        $teamType = DB::table('eventtypes')->insertGetId([
            'name' => 'Manual replacement event', 'type' => 2, 'code' => 'manual-replacement-event',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $source->event->update(['eventType' => $teamType]);
        EventAdmin::create(['event_id' => $source->event_id, 'user_id' => $manager->id]);
        $selectionImport = app(TeamRankingImportService::class)->import($source, $manager);
        $selectionImport->update([
            'status' => 'sent',
            'response_deadline' => now()->addDay(),
            'payment_deadline' => now()->addDays(2),
            'replacement_payment_deadline' => now()->addDays(3),
        ]);

        $this->actingAs($manager)->patch(
            route('backend.team-selection.replacement-mode.update', [$source->event, $selectionImport]),
            [
                'replacement_mode' => 'manual',
            ]
        )->assertRedirect()->assertSessionHas('success');
        $this->assertFalse($selectionImport->fresh()->auto_replacement_enabled);

        $declining = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)
            ->orderBy('queue_position')->firstOrFail();
        $reserve = $selectionImport->invitations()->where('status', TeamSelectionInvitation::RESERVE)
            ->orderBy('queue_position')->firstOrFail();
        $owner = User::findOrFail($declining->player->userId);
        $this->assertNull(app(TeamSelectionInvitationService::class)->decline($declining, $owner, 'Unavailable'));
        $this->assertSame(TeamSelectionInvitation::RESERVE, $reserve->fresh()->status);
        $this->assertNotNull($declining->fresh()->vacated_roster_rank);

        $this->actingAs($manager)->get(route('backend.team-selection.index', $source->event))
            ->assertOk()->assertSee('Manual approval')->assertSee('Invite next reserve');
        $this->actingAs($manager)->post(
            route('backend.team-selection.invitations.promote-reserve', [$source->event, $selectionImport, $declining])
        )->assertRedirect()->assertSessionHas('success');

        $reserve = $reserve->fresh();
        $this->assertSame(TeamSelectionInvitation::INVITED, $reserve->status);
        $this->assertNotNull($reserve->response_deadline_override);
        $this->assertNotNull($reserve->payment_deadline_override);
        $this->assertSame($reserve->payment_deadline_override->toDateTimeString(), $reserve->response_deadline_override->toDateTimeString());
        Queue::assertPushed(\App\Jobs\SendTeamSelectionInvitationEmailJob::class);
    }

    public function test_automatic_replacement_leaves_an_auditable_open_vacancy_when_no_eligible_reserve_exists(): void
    {
        Queue::fake();
        [$source] = $this->selectionSource();
        $manager = User::factory()->create();
        $teamType = DB::table('eventtypes')->insertGetId([
            'name' => 'No reserve replacement event', 'type' => 2, 'code' => 'no-reserve-replacement-event',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $source->event->update(['eventType' => $teamType]);
        EventAdmin::create(['event_id' => $source->event_id, 'user_id' => $manager->id]);
        $selectionImport = app(TeamRankingImportService::class)->import($source, $manager);
        $selectionImport->update([
            'status' => 'sent',
            'auto_replacement_enabled' => true,
            'response_deadline' => now()->addDay(),
            'payment_deadline' => now()->addDays(2),
            'replacement_payment_deadline' => now()->addDays(3),
        ]);
        $selectionImport->invitations()->where('status', TeamSelectionInvitation::RESERVE)->delete();
        $declining = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)
            ->orderBy('queue_position')->firstOrFail();
        $owner = User::findOrFail($declining->player->userId);

        $this->assertNull(app(TeamSelectionInvitationService::class)->decline($declining, $owner, 'Unavailable'));

        $declining = $declining->fresh();
        $this->assertSame(TeamSelectionInvitation::DECLINED, $declining->status);
        $this->assertNotNull($declining->vacated_roster_rank);
        Queue::assertNothingPushed();
        $this->actingAs($manager)->get(route('backend.team-selection.index', $source->event))
            ->assertOk()
            ->assertSee('Vacancy open')
            ->assertSee('no eligible reserve')
            ->assertSee('Add or link a reserve below.');
    }

    public function test_declining_a_draft_invitation_cancels_every_unpaid_registration_state(): void
    {
        Queue::fake();
        [$source, $team] = $this->selectionSource();
        $selectionImport = app(TeamRankingImportService::class)->import($source, User::factory()->create());
        $invitation = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)
            ->orderBy('queue_position')->firstOrFail();
        $reserve = $selectionImport->invitations()->where('status', TeamSelectionInvitation::RESERVE)
            ->orderBy('queue_position')->firstOrFail();
        $owner = User::factory()->create();

        app(TeamSelectionInvitationService::class)->accept($invitation, $owner);
        $order = app(TeamPaymentService::class)->ensureOrder(
            $owner,
            $team,
            $invitation->player,
            $selectionImport->event,
            490.00,
        );
        app(TeamPaymentService::class)->reservePayment($order, 100.00, 390.00);
        app(TeamSelectionInvitationService::class)->attachOrder($order);

        $replacement = app(TeamSelectionInvitationService::class)
            ->decline($invitation->fresh(), $owner, 'Unavailable');

        $this->assertSame($reserve->id, $replacement?->id);
        $this->assertSame(TeamSelectionInvitation::DECLINED, $invitation->fresh()->status);
        $this->assertNull($invitation->fresh()->roster_rank);
        $this->assertSame(0.0, (float) $order->fresh()->wallet_reserved);
        $this->assertSame(0.0, (float) $order->fresh()->payfast_amount_due);
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertFalse($order->fresh()->payfast_paid);
        $this->assertFalse($order->fresh()->wallet_debited);
        $this->assertFalse(TeamSelectionInvitation::query()
            ->where('player_id', $invitation->player_id)
            ->whereIn('status', [
                TeamSelectionInvitation::INVITED,
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                TeamSelectionInvitation::PAID_CONFIRMED,
            ])->exists());
        $this->assertSame($reserve->player_id, TeamPlayer::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('rank', $replacement->roster_rank)
            ->value('player_id'));
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
        $following = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)
            ->where('id', '!=', $selected->id)->orderBy('roster_rank')->firstOrFail();
        $vacatedRank = $selected->roster_rank;
        $finalRank = $following->roster_rank;
        $manager = User::factory()->create();

        $promoted = $service->replaceWithNextReserve($selected, $manager, 'Unavailable for the event weekend');

        $this->assertSame($reserve->id, $promoted->id);
        $this->assertSame(TeamSelectionInvitation::INVITED, $reserve->fresh()->status);
        $this->assertSame($finalRank, $reserve->fresh()->roster_rank);
        $this->assertSame($vacatedRank, $following->fresh()->roster_rank);
        $this->assertSame('regional_manager_replacement', $selected->fresh()->decline_method);
        $this->assertSame($manager->id, $selected->fresh()->declined_by_user_id);
        $this->assertSame('Unavailable for the event weekend', $selected->fresh()->decline_reason);
        $this->assertSame($following->player_id, TeamPlayer::withoutGlobalScopes()
            ->where('team_id', $team->id)->where('rank', $vacatedRank)->value('player_id'));
        $this->assertSame($reserve->player_id, TeamPlayer::withoutGlobalScopes()
            ->where('team_id', $team->id)->where('rank', $finalRank)->value('player_id'));
    }

    public function test_draft_replacement_can_promote_the_next_reserve_without_sending_email(): void
    {
        Queue::fake();
        [$source, $team] = $this->selectionSource();
        $selectionImport = app(TeamRankingImportService::class)->import($source, User::factory()->create());
        $selected = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)
            ->orderBy('queue_position')->firstOrFail();
        $reserve = $selectionImport->invitations()->where('status', TeamSelectionInvitation::RESERVE)
            ->orderBy('queue_position')->firstOrFail();
        $following = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)
            ->where('id', '!=', $selected->id)->orderBy('roster_rank')->firstOrFail();
        $vacatedRank = $selected->roster_rank;
        $finalRank = $following->roster_rank;

        $promoted = app(TeamSelectionInvitationService::class)
            ->replaceWithNextReserve($selected, User::factory()->create(), 'Draft roster correction');

        $this->assertSame($reserve->id, $promoted->id);
        $this->assertSame(TeamSelectionInvitation::INVITED, $reserve->fresh()->status);
        $this->assertSame($finalRank, $reserve->fresh()->roster_rank);
        $this->assertSame($vacatedRank, $following->fresh()->roster_rank);
        $this->assertNull($reserve->fresh()->invited_at);
        $this->assertSame($following->player_id, TeamPlayer::withoutGlobalScopes()
            ->where('team_id', $team->id)->where('rank', $vacatedRank)->value('player_id'));
        $this->assertSame($reserve->player_id, TeamPlayer::withoutGlobalScopes()
            ->where('team_id', $team->id)->where('rank', $finalRank)->value('player_id'));
        $this->assertDatabaseMissing('bulk_email_logs', [
            'related_type' => TeamSelectionInvitation::class,
            'related_id' => $reserve->id,
        ]);
    }

    public function test_manager_can_replace_an_unpaid_player_with_a_custom_linked_profile(): void
    {
        Queue::fake();
        [$source, $team] = $this->selectionSource();
        $team->update(['num_team_members' => 3]);
        $teamType = DB::table('eventtypes')->insertGetId([
            'name' => 'Custom profile replacement', 'type' => 2, 'code' => 'custom-profile-replacement',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $source->event->update(['eventType' => $teamType]);
        $manager = User::factory()->create();
        EventAdmin::create(['event_id' => $source->event_id, 'user_id' => $manager->id]);
        $selectionImport = app(TeamRankingImportService::class)->import($source, $manager);
        $selected = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)
            ->orderBy('queue_position')->firstOrFail();
        $following = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)
            ->where('id', '!=', $selected->id)->orderBy('roster_rank')->get();
        $this->assertCount(2, $following);
        $vacatedRank = $selected->roster_rank;
        $finalRank = $following->last()->roster_rank;
        $owner = User::factory()->create(['email' => 'custom.player@example.test']);
        $customPlayer = Player::factory()->create([
            'name' => 'Custom', 'surname' => 'Selection',
            'email' => 'custom.player@example.test', 'userId' => $owner->id,
        ]);

        $this->actingAs(User::factory()->create())->post(
            route('backend.team-selection.invitations.replace', [$source->event, $selectionImport, $selected]),
            [
                'replacement_mode' => 'custom_profile',
                'replacement_player_id' => $customPlayer->id,
                'replacement_invitation_id' => $selected->id,
                'reason' => 'Unauthorized replacement attempt',
            ]
        )->assertForbidden();
        $this->assertFalse($selectionImport->invitations()->where('player_id', $customPlayer->id)->exists());

        $this->actingAs($manager)->post(
            route('backend.team-selection.invitations.replace', [$source->event, $selectionImport, $selected]),
            [
                'replacement_mode' => 'custom_profile',
                'replacement_player_id' => $customPlayer->id,
                'replacement_invitation_id' => $selected->id,
                'reason' => 'Captain selected an alternate profile',
            ]
        )->assertRedirect()->assertSessionHas('success');

        $replacement = $selectionImport->invitations()->where('player_id', $customPlayer->id)->firstOrFail();
        $this->assertSame(TeamSelectionInvitation::INVITED, $replacement->status);
        $this->assertSame($finalRank, $replacement->roster_rank);
        $this->assertSame($vacatedRank, $following->first()->fresh()->roster_rank);
        $this->assertSame($finalRank - 1, $following->last()->fresh()->roster_rank);
        $this->assertNull($replacement->invited_at);
        $this->assertSame('manual_system_profile', $replacement->snapshot_json['selection_source']);
        $this->assertSame($selected->id, $replacement->snapshot_json['replaces_invitation_id']);
        $this->assertSame('regional_manager_custom_profile', $selected->fresh()->decline_method);
        $this->assertSame($following->first()->player_id, TeamPlayer::withoutGlobalScopes()
            ->where('team_id', $team->id)->where('rank', $vacatedRank)->value('player_id'));
        $this->assertSame($following->last()->player_id, TeamPlayer::withoutGlobalScopes()
            ->where('team_id', $team->id)->where('rank', $finalRank - 1)->value('player_id'));
        $this->assertSame($customPlayer->id, TeamPlayer::withoutGlobalScopes()
            ->where('team_id', $team->id)->where('rank', $finalRank)->value('player_id'));
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TeamSelectionInvitation::class,
            'subject_id' => $selected->id,
            'description' => 'regional manager replaced selected player with custom system profile',
        ]);
        $this->assertDatabaseMissing('bulk_email_logs', [
            'related_type' => TeamSelectionInvitation::class,
            'related_id' => $replacement->id,
        ]);
        $this->actingAs($manager)->get(route('backend.team-selection.index', $source->event))
            ->assertOk()
            ->assertSee('bg-label-danger">Declined', false)
            ->assertDontSee('bg-label-primary">Rank </span>', false);

        $unlinkedPlayer = Player::factory()->create(['userId' => null, 'email' => null]);
        $this->actingAs($manager)->post(
            route('backend.team-selection.invitations.replace', [$source->event, $selectionImport, $replacement]),
            [
                'replacement_mode' => 'custom_profile',
                'replacement_player_id' => $unlinkedPlayer->id,
                'replacement_invitation_id' => $replacement->id,
                'reason' => 'Unlinked profile attempt',
            ]
        )->assertSessionHasErrors('replacement_player_id');
        $this->assertSame(TeamSelectionInvitation::INVITED, $replacement->fresh()->status);

        $this->actingAs($manager)->post(
            route('backend.team-selection.invitations.replace', [$source->event, $selectionImport, $replacement]),
            [
                'replacement_mode' => 'custom_profile',
                'replacement_player_id' => $customPlayer->id,
                'replacement_invitation_id' => $replacement->id,
                'reason' => 'Duplicate profile attempt',
            ]
        )->assertSessionHasErrors('replacement_player_id');
    }

    public function test_sent_custom_profile_replacement_queues_the_saved_invitation_email(): void
    {
        Queue::fake();
        [$source] = $this->selectionSource();
        $selectionImport = app(TeamRankingImportService::class)->import($source, User::factory()->create());
        $selectionImport->update([
            'status' => 'sent',
            'response_deadline' => now()->addDay(),
            'payment_deadline' => now()->addDays(2),
            'replacement_payment_deadline' => now()->addDays(3),
            'email_subject' => 'Saved regional invitation',
            'email_message' => 'Please confirm your team place.',
        ]);
        $selected = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)
            ->orderBy('queue_position')->firstOrFail();
        $owner = User::factory()->create(['email' => 'sent.custom@example.test']);
        $customPlayer = Player::factory()->create([
            'name' => 'Sent', 'surname' => 'Custom',
            'email' => 'sent.custom@example.test', 'userId' => $owner->id,
        ]);

        $replacement = app(TeamSelectionInvitationService::class)->replaceWithSystemPlayer(
            $selected,
            $customPlayer,
            User::factory()->create(),
            'Sent selection correction'
        );

        $this->assertNotNull($replacement->invited_at);
        $this->assertDatabaseHas('bulk_email_logs', [
            'mail_type' => 'team_selection_invitation',
            'related_type' => TeamSelectionInvitation::class,
            'related_id' => $replacement->id,
            'recipient_email' => 'sent.custom@example.test',
            'status' => 'queued',
        ]);
        Queue::assertPushed(\App\Jobs\SendTeamSelectionInvitationEmailJob::class);
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

    public function test_any_signed_in_user_can_open_an_enabled_paid_player_clothing_catalogue(): void
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
        $customerPrice = app(ClothingPriceService::class)->totals((float) $item->price)['total'];

        $this->actingAs($owner)->get(route('team-selection.invitations.clothing', $invitation))
            ->assertOk()
            ->assertSee('Regional tracksuit')
            ->assertSee('R'.number_format($customerPrice, 2))
            ->assertDontSee('PayFast fee')
            ->assertSee('Total payable');
        $this->actingAs(User::factory()->create())
            ->get(route('team-selection.invitations.clothing', $invitation))
            ->assertOk()
            ->assertSee('R'.number_format($customerPrice, 2));
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
        $venue = new Venue();
        $venue->name = 'Boland Park Tennis Centre';
        $venue->save();
        $event->venues()->attach($venue->id, ['num_courts' => 4]);
        $region = $selectionImport->region;
        $region->update(['clothing_admin' => true, 'clothing_order' => true]);
        $shirt = ClothingItemType::create([
            'region_id' => $region->id,
            'item_type_name' => 'Regional match shirt',
            'price' => 395,
            'ordering' => 1,
        ]);
        ClothingSize::create(['item_type' => $shirt->id, 'size' => '11-12', 'ordering' => 1]);
        $shirtFinalPrice = app(ClothingPriceService::class)->totals((float) $shirt->price)['total'];
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

        $eventWithLegacyVenueAttribute = $event->fresh()->load('venues');
        $eventWithLegacyVenueAttribute->setAttribute('venues', 'Legacy venue text');
        $selectionImport->setRelation('event', $eventWithLegacyVenueAttribute);
        $campaign = app(TeamSelectionInvitationService::class)->previewCampaign($selectionImport, $payload);
        $this->assertSame(['Boland Park Tennis Centre'], $campaign['event']['venues']);

        $this->actingAs($admin)->post(route('backend.team-selection.email.preview', [$event, $selectionImport]), $payload)
            ->assertOk()
            ->assertSee('Preview only — no email has been sent')
            ->assertSee('A message written by the regional organiser.')
            ->assertDontSee('Meet the team manager at 07:30.')
            ->assertDontSee('Cape Tennis Events Team')
            ->assertDontSee('Boland Park Tennis Centre')
            ->assertDontSee('Players must report 30 minutes before their first match.')
            ->assertDontSee('Event information')
            ->assertSee('View event')
            ->assertSee('How to register')
            ->assertSee('Regional match shirt')
            ->assertSee('R'.number_format($shirtFinalPrice, 2))
            ->assertSee('Sizes: 11-12')
            ->assertSee('How to order:')
            ->assertSee('go to the main page and click')
            ->assertSee('Order clothing')
            ->assertDontSee('return to your invitation page')
            ->assertSee('Register and pay')
            ->assertSee('Decline invitation')
            ->assertDontSee('Ranking position:')
            ->assertDontSee('Entry fee');

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
        $this->assertEquals($shirtFinalPrice, $savedImport->communication_snapshot['clothing_items'][0]['price']);
        $sentInvitation = $savedImport->invitations()->where('status', TeamSelectionInvitation::INVITED)->firstOrFail();
        $this->actingAs($admin)->get(route('backend.team-selection.invitations.email.view', [$event, $savedImport, $sentInvitation]))
            ->assertOk()
            ->assertSee('Saved invitation email — read-only campaign snapshot')
            ->assertSee('This wording changed after preview.');
        $this->actingAs($admin)->post(route('backend.team-selection.invitations.email.resend', [$event, $savedImport, $sentInvitation]))
            ->assertRedirect();
        $this->assertSame(2, BulkEmailLog::where('related_type', TeamSelectionInvitation::class)
            ->where('related_id', $sentInvitation->id)->count());
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
            ->assertSee('Tournament workspace')
            ->assertSee('>Teams</a>', false)
            ->assertDontSee('Back to event')
            ->assertSee(route('events.show', $event), false)
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
        $this->actingAs($manager)->get(route('backend.region.clothing.edit', ['region' => $firstRegion, 'event_id' => $event->id]))
            ->assertOk()
            ->assertSee('Back to event')
            ->assertSee(route('backend.team-selection.index', $event), false);
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
        $otherTeam = new Team();
        $otherTeam->forceFill(['name' => 'Private team', 'region_id' => $secondRegion->id,
            'category_event_id' => $categoryEvent->id, 'num_team_members' => 2, 'published' => false,
            'user_id' => $admin->id, 'personal_team' => false])->save();
        $this->actingAs($manager)->patch(route('backend.team-selection.teams.update', [$event, $second, $otherTeam]), [
            'name' => 'Must stay private', 'published' => 1,
        ])->assertForbidden();
        $this->assertDatabaseHas('teams', ['id' => $otherTeam->id, 'name' => 'Private team', 'published' => 0]);
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
        Queue::fake();
        [$source, $team, $players] = $this->selectionSource();
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
        $ordered = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)->orderBy('roster_rank')->get();
        $this->actingAs($manager)->post(route('backend.team-selection.invitations.move', [$event, $selectionImport, $ordered->last()]), [
            'direction' => 'up',
        ])->assertRedirect();
        $this->assertSame(1, $ordered->last()->fresh()->roster_rank);
        $this->assertSame($ordered->last()->player_id, TeamPlayer::withoutGlobalScopes()
            ->where('team_id', $team->id)->where('rank', 1)->value('player_id'));
        $this->actingAs($manager)->post(route('backend.team-selection.roster-email.send', [$event, $eventRegion]), [
            'target_type' => 'team',
            'team_id' => $team->id,
            'subject' => 'Regional team update',
            'message' => 'Please note the updated team information.',
            'confirm_recipients' => 1,
        ])->assertRedirect();
        $this->assertSame(2, BulkEmailLog::where('mail_type', 'team_email')->where('related_id', $team->id)->count());
        $recipientEmails = $selectionImport->invitations()
            ->whereIn('status', [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, TeamSelectionInvitation::PAID_CONFIRMED])
            ->with(['player.user', 'player.users'])->get()
            ->map(fn (TeamSelectionInvitation $invitation) => collect([$invitation->player?->user?->email, $invitation->player?->email])
                ->merge($invitation->player?->users?->pluck('email') ?? collect())
                ->map(fn ($email) => mb_strtolower(trim((string) $email)))
                ->first(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL)))
            ->filter()->unique()->sort()->values();
        $this->actingAs($manager)->post(route('backend.team-selection.roster-email.send', [$event, $eventRegion]), [
            'target_type' => 'region',
            'subject' => 'Whole region update',
            'message' => 'Please note the regional information.',
            'confirm_recipients' => 1,
            'recipient_hash' => hash('sha256', $recipientEmails->toJson()),
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame($recipientEmails->count(), BulkEmailLog::where('mail_type', 'region_email')->where('related_id', $eventRegion->id)->count());
        $this->actingAs($manager)->from(route('backend.team-selection.index', $event))
            ->post(route('backend.team-selection.roster-email.send', [$event, $eventRegion]), [
                'target_type' => 'region',
                'subject' => 'Stale whole region update',
                'message' => 'This stale recipient list must not be queued.',
                'confirm_recipients' => 1,
                'recipient_hash' => str_repeat('0', 64),
            ])->assertRedirect(route('backend.team-selection.index', $event))
            ->assertSessionHasErrors('confirm_recipients');
        $this->assertSame($recipientEmails->count(), BulkEmailLog::where('mail_type', 'region_email')->where('related_id', $eventRegion->id)->count());
        $this->actingAs($manager)->get(route('backend.team-selection.index', $event))
            ->assertOk()
            ->assertSee('Tournament workspace')
            ->assertSee('event-workspace-chrome', false)
            ->assertSee(route('backend.team-selection.index', $event), false)
            ->assertSee('>Teams</a>', false)
            ->assertDontSee(route('admin.events.overview', $event), false)
            ->assertDontSee('>Event overview</a>', false)
            ->assertDontSee('>Draws</a>', false)
            ->assertDontSee('>Results</a>', false)
            ->assertDontSee('>Finances</a>', false)
            ->assertDontSee('>Settings</a>', false)
            ->assertDontSee('>More</button>', false)
            ->assertSee('Regional teams & players')
            ->assertSee('Region-scoped workspace')
            ->assertSee($team->name)
            ->assertSee($players->first()->full_name)
            ->assertSee('Rank 1')
            ->assertSee('Show team')
            ->assertSee('data-team-workspace-header', false)
            ->assertSee('data-team-workspace-target="#team-workspace-'.$team->id.'"', false)
            ->assertSee('class="btn btn-sm btn-outline-primary team-settings-toggle"', false)
            ->assertSee('aria-controls="team-settings-'.$team->id.'"', false)
            ->assertSeeInOrder([
                'id="team-settings-'.$team->id.'"',
                'id="team-workspace-'.$team->id.'"',
            ], false)
            ->assertSee('name="settings_team_id" value="'.$team->id.'"', false)
            ->assertSee('Player order')
            ->assertSee('Email team')
            ->assertSee('Email all players in region')
            ->assertSee('Send all invitations')
            ->assertSee('data-bs-target="#prepare-invitations-'.$selectionImport->id.'"', false)
            ->assertSee('Change player')
            ->assertSee('data-replacement-player-form', false)
            ->assertSee('data-replacement-mode', false)
            ->assertSee('Choose a Cape Tennis player profile')
            ->assertSee('Search player name, email or cell')
            ->assertSee('value="Player not available."', false)
            ->assertSee('Selection / payment')
            ->assertSee('Read only')
            ->assertSee('Ranking snapshot:')
            ->assertDontSee('Private Other Region');
        $this->actingAs($manager)->from(route('backend.team-selection.index', $event))
            ->patch(route('backend.team-selection.teams.update', [$event, $eventRegion, $team]), [
                'settings_team_id' => $team->id,
                'name' => '',
                'published' => 1,
            ])->assertRedirect(route('backend.team-selection.index', $event))->assertSessionHasErrors('name');
        $this->actingAs($manager)->get(route('backend.team-selection.index', $event))
            ->assertOk()
            ->assertSee('const settingsTeamId = '.$team->id.';', false);
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

    public function test_profile_email_allows_a_reserve_without_a_linked_account(): void
    {
        Queue::fake();
        [$source] = $this->selectionSource();
        $selectionImport = app(TeamRankingImportService::class)->import($source, User::factory()->create());
        $reserve = $selectionImport->invitations()->where('status', TeamSelectionInvitation::RESERVE)
            ->firstOrFail();
        $reserve->player->update(['email' => 'reserve.profile@example.test', 'userId' => null]);
        $reserve->player->users()->detach();

        app(TeamSelectionInvitationService::class)->send($selectionImport, [
            'response_deadline' => now()->addDay(),
            'payment_deadline' => now()->addDays(2),
        ], User::factory()->create());

        $this->assertSame('sent', $selectionImport->fresh()->status);
    }

    public function test_profile_email_is_primary_and_linked_email_is_the_fallback(): void
    {
        Queue::fake();
        [$source] = $this->selectionSource();
        $selectionImport = app(TeamRankingImportService::class)->import($source, User::factory()->create());
        $selected = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)
            ->orderBy('roster_rank')->get();
        $primary = $selected->firstOrFail();
        $fallback = $selected->last();
        $primary->player->update(['email' => 'player.primary@example.test']);
        $primary->player->user->update(['email' => 'parent.fallback@example.test']);
        $linkedFallback = User::factory()->create(['email' => 'linked.fallback@example.test']);
        $fallback->player->update(['email' => null, 'userId' => null]);
        $fallback->player->users()->sync([$linkedFallback->id]);

        app(TeamSelectionInvitationService::class)->send($selectionImport, [
            'response_deadline' => now()->addDay(),
            'payment_deadline' => now()->addDays(2),
        ], User::factory()->create());

        $this->assertDatabaseHas('bulk_email_logs', [
            'related_type' => TeamSelectionInvitation::class,
            'related_id' => $primary->id,
            'recipient_email' => 'player.primary@example.test',
        ]);
        $this->assertDatabaseHas('bulk_email_logs', [
            'related_type' => TeamSelectionInvitation::class,
            'related_id' => $fallback->id,
            'recipient_email' => 'linked.fallback@example.test',
        ]);
        $this->assertDatabaseMissing('bulk_email_logs', [
            'related_type' => TeamSelectionInvitation::class,
            'related_id' => $primary->id,
            'recipient_email' => 'parent.fallback@example.test',
        ]);
    }

    public function test_selection_page_uses_valid_profile_email_before_an_invalid_linked_email(): void
    {
        Role::findOrCreate('admin', 'web');
        [$source, , $players] = $this->selectionSource();
        $teamEventType = DB::table('eventtypes')->insertGetId([
            'name' => 'Team link display test', 'type' => 2, 'code' => 'team-link-display-test',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $source->event->update(['eventType' => $teamEventType]);
        $admin = User::factory()->create()->assignRole('admin');
        EventAdmin::create(['event_id' => $source->event_id, 'user_id' => $admin->id]);
        $invalidAccount = User::factory()->create(['email' => 'tsargeant@shprite']);
        $players->first()->update([
            'email' => 'family@example.test',
            'userId' => $invalidAccount->id,
        ]);
        app(TeamRankingImportService::class)->import($source, $admin);

        $this->actingAs($admin)->get(route('backend.team-selection.index', $source->event))
            ->assertOk()
            ->assertSee('family@example.test')
            ->assertDontSee('Profile/account email invalid')
            ->assertDontSee('Email required');
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
        $following = $selectionImport->invitations()->where('status', TeamSelectionInvitation::INVITED)
            ->where('id', '!=', $withdrawing->id)->orderBy('roster_rank')->firstOrFail();
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
        $this->assertSame($following->player_id, TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('rank', 1)->value('player_id'));
        $this->assertSame($reserve->player_id, TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('rank', 2)->value('player_id'));
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

    public function test_expired_responses_promote_the_next_reserve_without_leaving_a_roster_gap(): void
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
        $selectionImport->update(['response_deadline' => now()->subMinute()]);

        $rows = $service->processExpiredInvitations($selectionImport->event_id, true);

        $this->assertTrue(collect($rows)->contains(fn (array $row) => $row['invitation_id'] === $selected->id));
        $this->assertSame(TeamSelectionInvitation::DECLINED, $selected->fresh()->status);
        $this->assertSame('system_response_deadline', $selected->fresh()->decline_method);
        $this->assertSame(TeamSelectionInvitation::INVITED, $reserve->fresh()->status);
        $this->assertSame(1, $reserve->fresh()->roster_rank);
        $this->assertSame($reserve->player_id, TeamPlayer::withoutGlobalScopes()
            ->where('team_id', $team->id)->where('rank', 1)->value('player_id'));
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

    public function test_manager_can_search_and_add_any_system_player_as_an_audited_reserve(): void
    {
        [$source, $team] = $this->selectionSource();
        $manager = User::factory()->create();
        $teamType = DB::table('eventtypes')->insertGetId([
            'name' => 'Manual reserve team event', 'type' => 2, 'code' => 'manual-reserve-team-event',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $source->event->update(['eventType' => $teamType]);
        EventAdmin::create(['event_id' => $source->event_id, 'user_id' => $manager->id]);
        $selectionImport = app(TeamRankingImportService::class)->import($source, $manager);
        $owner = User::factory()->create(['email' => 'new.reserve@example.test']);
        $player = Player::factory()->create([
            'name' => 'Brandnew',
            'surname' => 'Reserveplayer',
            'email' => 'new.reserve@example.test',
            'userId' => $owner->id,
        ]);
        $unlinked = Player::factory()->create([
            'name' => 'Ian',
            'surname' => 'Ferreira',
            'userId' => null,
            'email' => null,
            'cellNr' => '0821234567',
        ]);
        $rosterBefore = TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)
            ->orderBy('rank')->pluck('player_id')->all();

        $this->actingAs(User::factory()->create())
            ->getJson(route('backend.team-selection.players.search', [$source->event, $selectionImport, $team, 'q' => 'Brandnew']))
            ->assertForbidden();

        $this->actingAs($manager)
            ->getJson(route('backend.team-selection.players.search', [$source->event, $selectionImport, $team, 'q' => 'Brandnew']))
            ->assertOk()->assertJsonPath('results.0.id', $player->id)
            ->assertJsonPath('results.0.text', 'Brandnew Reserveplayer · new.reserve@example.test');
        $this->actingAs($manager)
            ->getJson(route('backend.team-selection.players.search', [$source->event, $selectionImport, $team, 'q' => 'Ian Ferreira']))
            ->assertOk()
            ->assertJsonPath('results.0.id', $unlinked->id)
            ->assertJsonPath('results.0.text', 'Ian Ferreira · 0821234567');

        $this->actingAs($manager)->post(
            route('backend.team-selection.players.add', [$source->event, $selectionImport, $team]),
            ['player_id' => $player->id, 'reason' => 'Late regional selection', 'add_team_id' => $team->id]
        )->assertRedirect()->assertSessionHas('success');

        $invitation = $selectionImport->invitations()->where('player_id', $player->id)->firstOrFail();
        $this->assertSame(TeamSelectionInvitation::RESERVE, $invitation->status);
        $this->assertNull($invitation->roster_rank);
        $this->assertSame('manual_system_profile', $invitation->snapshot_json['selection_source']);
        $this->assertSame('Late regional selection', $invitation->snapshot_json['reason']);
        $this->assertSame($rosterBefore, TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)
            ->orderBy('rank')->pluck('player_id')->all());
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TeamSelectionInvitation::class,
            'subject_id' => $invitation->id,
            'description' => 'regional manager added system player profile as reserve',
        ]);

        $this->actingAs($manager)->post(
            route('backend.team-selection.players.add', [$source->event, $selectionImport, $team]),
            ['player_id' => $unlinked->id, 'reason' => 'System-wide player search', 'add_team_id' => $team->id]
        )->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('team_selection_invitations', [
            'import_id' => $selectionImport->id,
            'team_id' => $team->id,
            'player_id' => $unlinked->id,
            'status' => TeamSelectionInvitation::RESERVE,
        ]);

        $this->actingAs($manager)->get(route('backend.team-selection.index', $source->event))
            ->assertOk()
            ->assertSee('Add an existing system player profile')
            ->assertSee('Add as reserve')
            ->assertSee('Manual addition')
            ->assertSee('/assets/vendor/fonts/tabler/tabler-icons.woff2?v=20260910', false);

        $this->actingAs($manager)->post(
            route('backend.team-selection.players.add', [$source->event, $selectionImport, $team]),
            ['player_id' => $player->id, 'reason' => 'Duplicate attempt', 'add_team_id' => $team->id]
        )->assertSessionHasErrors('player_id');
        $this->assertSame(1, $selectionImport->invitations()->where('team_id', $team->id)
            ->where('player_id', $player->id)->count());
        $this->assertTrue($selectionImport->invitations()->where('player_id', $unlinked->id)->exists());
    }

    public function test_ranked_primary_reserve_can_help_another_team_and_returns_to_real_team_when_promoted(): void
    {
        [$source, $primaryTeam, $players] = $this->selectionSource();
        $manager = User::factory()->create();
        $teamType = DB::table('eventtypes')->insertGetId([
            'name' => 'Shared reserve team event', 'type' => 2, 'code' => 'shared-reserve-team-event',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $source->event->update(['eventType' => $teamType]);
        EventAdmin::create(['event_id' => $source->event_id, 'user_id' => $manager->id]);
        $selectionImport = app(TeamRankingImportService::class)->import($source, $manager);
        $primaryPlayer = $players->get(2);
        $primaryPlayer->update(['name' => 'Rian', 'surname' => 'Fourie']);
        $primaryReserve = $selectionImport->invitations()->where('team_id', $primaryTeam->id)
            ->where('player_id', $primaryPlayer->id)->firstOrFail();
        $this->assertSame(TeamSelectionInvitation::RESERVE, $primaryReserve->status);

        $helperTeam = new Team();
        $helperTeam->forceFill([
            'user_id' => $manager->id,
            'personal_team' => false,
            'name' => 'Overberg helper group',
            'region_id' => $primaryTeam->region_id,
            'num_team_members' => 2,
            'published' => true,
            'year' => $primaryTeam->year,
        ])->save();
        $template = $selectionImport->invitations()->where('team_id', $primaryTeam->id)->firstOrFail();
        $helperPlayer = Player::factory()->create();
        TeamPlayer::create([
            'team_id' => $helperTeam->id,
            'player_id' => $helperPlayer->id,
            'rank' => 1,
            'pay_status' => 0,
        ]);
        $helperSelected = TeamSelectionInvitation::create([
            'import_id' => $selectionImport->id,
            'event_id' => $selectionImport->event_id,
            'region_id' => $selectionImport->region_id,
            'team_id' => $helperTeam->id,
            'player_id' => $helperPlayer->id,
            'ranking_list_id' => $template->ranking_list_id,
            'ranking_position' => 1,
            'queue_position' => 1,
            'total_points' => 0,
            'roster_rank' => 1,
            'status' => TeamSelectionInvitation::INVITED,
        ]);

        $service = app(TeamSelectionInvitationService::class);

        $this->actingAs($manager)
            ->getJson(route('backend.team-selection.players.search', [
                $source->event, $selectionImport, $helperTeam, 'q' => 'Rian Fourie',
            ]))
            ->assertOk()
            ->assertJsonPath('results.0.id', $primaryPlayer->id);

        $helperPlacement = $service->replaceWithSystemPlayer(
            $helperSelected,
            $primaryPlayer,
            $manager,
            'Available to help the older age group'
        );
        $this->assertSame(TeamSelectionInvitation::INVITED, $helperPlacement->status);
        $this->assertSame(TeamSelectionInvitation::RESERVE, $primaryReserve->fresh()->status);

        $selectionImport->invitations()->where('team_id', $primaryTeam->id)
            ->where('status', TeamSelectionInvitation::RESERVE)
            ->where('player_id', '!=', $primaryPlayer->id)
            ->update([
                'status' => TeamSelectionInvitation::WITHDRAWN,
                'declined_at' => now(),
                'decline_reason' => 'Test queue preparation.',
            ]);
        $selected = $selectionImport->invitations()->where('team_id', $primaryTeam->id)
            ->where('status', TeamSelectionInvitation::INVITED)
            ->orderBy('roster_rank')->firstOrFail();

        $promoted = $service->replaceWithNextReserve($selected, $manager, 'Primary-team place became available.');

        $this->assertSame($primaryReserve->id, $promoted->id);
        $this->assertSame(TeamSelectionInvitation::INVITED, $primaryReserve->fresh()->status);
        $this->assertSame(TeamSelectionInvitation::WITHDRAWN, $helperPlacement->fresh()->status);
        $this->assertSame('system_primary_team_promotion', $helperPlacement->fresh()->decline_method);
        $this->assertSame(
            'This player has been put into the real team: '.$primaryTeam->name.'.',
            $helperPlacement->fresh()->decline_reason
        );
        $this->assertSame(0, TeamPlayer::withoutGlobalScopes()->where('team_id', $helperTeam->id)
            ->where('rank', 1)->value('player_id'));
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TeamSelectionInvitation::class,
            'subject_id' => $helperPlacement->id,
            'description' => 'moved helper player into primary regional team',
        ]);
        $this->actingAs($manager)->get(route('backend.team-selection.index', $source->event))
            ->assertOk()
            ->assertSee('This player has been put into the real team: '.$primaryTeam->name.'.');
    }

    public function test_manager_can_activate_reserves_into_unfilled_configured_team_places(): void
    {
        [$source, $team] = $this->selectionSource();
        $manager = User::factory()->create();
        $teamType = DB::table('eventtypes')->insertGetId([
            'name' => 'Open roster team event', 'type' => 2, 'code' => 'open-roster-team-event',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $source->event->update(['eventType' => $teamType]);
        EventAdmin::create(['event_id' => $source->event_id, 'user_id' => $manager->id]);
        $selectionImport = app(TeamRankingImportService::class)->import($source, $manager);
        $team->update(['num_team_members' => 4]);

        $service = app(TeamSelectionInvitationService::class);
        $first = Player::factory()->create(['name' => 'Seventh', 'surname' => 'Player']);
        $second = Player::factory()->create(['name' => 'Eighth', 'surname' => 'Player']);
        $third = Player::factory()->create(['name' => 'Extra', 'surname' => 'Reserve']);
        $firstReserve = $service->addSystemPlayerAsReserve($selectionImport, $team, $first, $manager, 'Fill open place seven');
        $secondReserve = $service->addSystemPlayerAsReserve($selectionImport, $team, $second, $manager, 'Fill open place eight');
        $thirdReserve = $service->addSystemPlayerAsReserve($selectionImport, $team, $third, $manager, 'Remain in reserve queue');

        $this->actingAs($manager)->get(route('backend.team-selection.index', $source->event))
            ->assertOk()
            ->assertSee('Activate as Rank 3')
            ->assertSee('Activate as Rank 4');

        $this->actingAs(User::factory()->create())->post(route('backend.team-selection.invitations.activate', [
            $source->event, $selectionImport, $firstReserve,
        ]))->assertForbidden();

        $this->actingAs($manager)->post(route('backend.team-selection.invitations.activate', [
            $source->event, $selectionImport, $firstReserve,
        ]))->assertRedirect()->assertSessionHas('success');
        $this->actingAs($manager)->post(route('backend.team-selection.invitations.activate', [
            $source->event, $selectionImport, $secondReserve,
        ]))->assertRedirect()->assertSessionHas('success');

        $this->assertSame(3, $firstReserve->fresh()->roster_rank);
        $this->assertSame(4, $secondReserve->fresh()->roster_rank);
        $this->assertSame(TeamSelectionInvitation::INVITED, $firstReserve->fresh()->status);
        $this->assertSame(TeamSelectionInvitation::INVITED, $secondReserve->fresh()->status);
        $this->assertSame(TeamSelectionInvitation::RESERVE, $thirdReserve->fresh()->status);
        $this->assertSame(
            [$first->id, $second->id],
            TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)
                ->whereIn('rank', [3, 4])->orderBy('rank')->pluck('player_id')->all()
        );
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TeamSelectionInvitation::class,
            'subject_id' => $firstReserve->id,
            'description' => 'activated reserve in open regional team place',
        ]);

        $this->actingAs($manager)->post(route('backend.team-selection.invitations.activate', [
            $source->event, $selectionImport, $thirdReserve,
        ]))->assertSessionHasErrors('activation');
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
