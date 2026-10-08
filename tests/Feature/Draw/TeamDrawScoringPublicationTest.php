<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\DrawPublicationService;
use App\Models\{CategoryEvent, Draw, DrawAuditLog, Event, EventType, Player, Team, TeamFixture, TeamFixturePlayer, TeamFixtureResult, TeamPlayer, TeamTie, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamDrawScoringPublicationTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('eventtypes')->insert(['id' => 3, 'name' => 'Team', 'type' => EventType::TEAM]);
        $this->event = Event::factory()->create(['eventType' => 3]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
        $this->actingAs($this->admin);
    }

    private function readyDraw(int $tieCount = 2): Draw
    {
        $snapshot = ['min_roster_size' => 1, 'max_roster_size' => 2, 'allow_player_reuse' => false,
            'rubbers' => [['sequence' => 1, 'is_required' => true, 'rubber_code' => 'singles']]];
        $draw = Draw::factory()->create(['event_id' => $this->event->id, 'team_format_snapshot' => $snapshot,
            'published' => false, 'oop_published' => false]);
        $category = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $teams = Team::factory()->count(2)->create(['category_event_id' => $category->id]);
        $draw->teams_in_draw()->attach($teams->pluck('id'));
        $players = [];
        foreach ($teams as $team) {
            $player = Player::factory()->create();
            TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1]);
            $players[] = $player->id;
        }
        for ($number = 1; $number <= $tieCount; $number++) {
            $tie = TeamTie::create(['draw_id' => $draw->id, 'round_nr' => $number, 'tie_nr' => 1,
                'home_team_id' => $teams[0]->id, 'away_team_id' => $teams[1]->id,
                'status' => TeamTie::STATUS_DRAFT, 'format_snapshot' => $snapshot]);
            $fixture = TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id,
                'round_nr' => $number, 'tie_nr' => 1, 'match_nr' => $number, 'rubber_sequence' => 1,
                'rubber_code' => 'singles', 'rubber_name' => 'Singles', 'player_count_per_team' => 1,
                'match_status' => 0]);
            TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 1,
                'team1_id' => $players[0], 'team2_id' => $players[1]]);
        }
        return $draw;
    }

    public function test_publish_enables_every_tie_and_leaves_schedule_and_on_court_state_unchanged(): void
    {
        $draw = $this->readyDraw();
        $this->postJson(route('draw.toggle.publish', $draw))->assertOk()->assertJsonPath('published', true)
            ->assertJsonPath('scoring.ready', true);
        $this->assertSame(2, $draw->teamTies()->where('status', TeamTie::STATUS_PUBLISHED)->whereNotNull('published_at')->count());
        $this->assertFalse((bool) $draw->fresh()->oop_published);
        $this->assertSame(0, $draw->fixtures()->where('match_status', '!=', 0)->count());
        $this->assertDatabaseCount('team_fixture_results', 0);
        $this->assertDatabaseHas('draw_audit_logs', ['draw_id' => $draw->id, 'action' => 'scoring_enabled']);
    }

    public function test_invalid_last_tie_rolls_back_all_ties_draw_and_audits(): void
    {
        $draw = $this->readyDraw();
        $draw->teamTies()->orderByDesc('id')->first()->rubbers()->first()->fixturePlayers()->delete();
        $this->postJson(route('draw.toggle.publish', $draw))->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertFalse((bool) $draw->fresh()->published);
        $this->assertSame(2, $draw->teamTies()->where('status', TeamTie::STATUS_DRAFT)->whereNull('published_at')->count());
        $this->assertDatabaseCount('draw_audit_logs', 0);
    }

    public function test_enable_scoring_is_public_only_and_idempotent_without_republishing(): void
    {
        $draw = $this->readyDraw();
        $this->postJson(route('draw.enable-scoring', $draw))->assertUnprocessable();
        $draw->update(['published' => true, 'oop_published' => true]);
        $this->postJson(route('draw.enable-scoring', $draw))->assertOk()->assertJsonPath('scoring.ready', true);
        $timestamps = $draw->teamTies()->pluck('published_at', 'id')->all();
        $this->postJson(route('draw.enable-scoring', $draw))->assertOk();
        app(DrawPublicationService::class)->publish($draw->fresh());
        $this->assertEquals($timestamps, $draw->teamTies()->pluck('published_at', 'id')->all());
        $this->assertSame(1, DrawAuditLog::where('draw_id', $draw->id)->where('action', 'scoring_enabled')->count());
        $this->assertSame(0, DrawAuditLog::where('draw_id', $draw->id)->where('action', 'published')->count());
        $this->assertTrue((bool) $draw->fresh()->published);
        $this->assertTrue((bool) $draw->fresh()->oop_published);
    }

    public function test_bulk_reconciles_public_draft_ties_and_reports_invalid_draw_without_partial_ties(): void
    {
        $ready = $this->readyDraw();
        $ready->update(['published' => true]);
        $invalid = $this->readyDraw();
        $invalid->teamTies()->orderByDesc('id')->first()->rubbers()->first()->fixturePlayers()->delete();
        $payload = ['operation' => 'draws', 'action' => 'publish', 'draw_ids' => [$ready->id, $invalid->id]];
        $this->postJson(route('backend.event-draws.bulk-publication', $this->event), $payload)->assertOk()
            ->assertJsonPath('success', false)->assertJsonCount(1, 'changed')->assertJsonCount(1, 'failed');
        $this->assertSame(2, $ready->teamTies()->where('status', TeamTie::STATUS_PUBLISHED)->count());
        $this->assertSame(2, $invalid->teamTies()->where('status', TeamTie::STATUS_DRAFT)->whereNull('published_at')->count());
        $this->assertFalse((bool) $invalid->fresh()->published);
        $this->postJson(route('backend.event-draws.bulk-publication', $this->event), array_replace($payload, ['draw_ids' => [$ready->id]]))
            ->assertOk()->assertJsonCount(1, 'unchanged')->assertJsonCount(0, 'changed');
    }

    public function test_foreign_admin_and_normal_user_cannot_enable_or_publish(): void
    {
        $draw = $this->readyDraw();
        $draw->update(['published' => true]);
        $foreign = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => Event::factory()->create()->id, 'user_id' => $foreign->id]);
        foreach ([$foreign, User::factory()->create()] as $actor) {
            $this->actingAs($actor)->postJson(route('draw.enable-scoring', $draw))->assertForbidden();
            $this->postJson(route('draw.toggle.publish', $draw))->assertForbidden();
        }
        $this->assertSame(2, $draw->teamTies()->where('status', TeamTie::STATUS_DRAFT)->count());
    }

    public function test_cross_event_team_player_and_cross_draw_match_are_rejected(): void
    {
        foreach (['team', 'player', 'fixture'] as $case) {
            $draw = $this->readyDraw(1);
            $tie = $draw->teamTies()->first();
            $rubber = $tie->rubbers()->first();
            if ($case === 'team') {
                $tie->homeTeam->update(['category_event_id' => CategoryEvent::factory()->create()->id]);
            } elseif ($case === 'player') {
                $rubber->fixturePlayers()->first()->update(['team1_id' => Player::factory()->create()->id]);
            } else {
                $rubber->update(['draw_id' => $this->readyDraw(1)->id]);
            }
            $draw->update(['published' => true]);
            $this->postJson(route('draw.enable-scoring', $draw))->assertUnprocessable();
            $this->assertSame(TeamTie::STATUS_DRAFT, $tie->fresh()->status);
        }
    }

    public function test_started_and_completed_published_ties_are_retained_while_upcoming_ties_enable(): void
    {
        $draw = $this->readyDraw(3);
        $ties = $draw->teamTies()->orderBy('id')->get();
        foreach ([$ties[0], $ties[1]] as $index => $tie) {
            $tie->update(['status' => $index ? TeamTie::STATUS_COMPLETED : TeamTie::STATUS_PUBLISHED,
                'published_at' => now()->subDay(), 'winner_team_id' => $index ? $tie->home_team_id : null]);
            $tie->rubbers()->first()->update(['match_status' => 1]);
        }
        $fixture = $ties[1]->rubbers()->first();
        TeamFixtureResult::create(['team_fixture_id' => $fixture->id, 'set_nr' => 1, 'team1_score' => 6, 'team2_score' => 2]);
        $before = $ties->take(2)->map(fn ($tie) => $tie->fresh()->getAttributes())->all();
        $draw->update(['published' => true]);
        $this->postJson(route('draw.enable-scoring', $draw))->assertOk();
        $this->assertEquals($before, $ties->take(2)->map(fn ($tie) => $tie->fresh()->getAttributes())->all());
        $this->assertDatabaseCount('team_fixture_results', 1);
        $this->assertSame(TeamTie::STATUS_PUBLISHED, $ties[2]->fresh()->status);
    }

    public function test_locked_draw_unpublished_played_tie_and_missing_snapshot_block_scoring(): void
    {
        foreach (['locked', 'played', 'snapshot'] as $case) {
            $draw = $this->readyDraw();
            $draw->update(['published' => true, 'locked' => $case === 'locked']);
            $tie = $draw->teamTies()->orderByDesc('id')->first();
            if ($case === 'played') $tie->rubbers()->first()->update(['match_status' => 1]);
            if ($case === 'snapshot') $tie->update(['format_snapshot' => []]);
            $this->postJson(route('draw.enable-scoring', $draw))->assertUnprocessable();
            $this->assertSame(2, $draw->teamTies()->where('status', TeamTie::STATUS_DRAFT)->whereNull('published_at')->count());
        }
    }

    public function test_convenors_and_assigned_scorers_cannot_enable_or_read_admin_readiness(): void
    {
        $draw = $this->readyDraw();
        $draw->update(['published' => true]);
        foreach (['convenor', 'score-keeper'] as $role) {
            $actor = User::factory()->create();
            DB::table('event_convenors')->insert(['event_id' => $this->event->id, 'user_id' => $actor->id, 'role' => $role]);
            $this->actingAs($actor)->postJson(route('draw.enable-scoring', $draw))->assertForbidden();
            $this->getJson(route('draw.scoring-readiness', $draw))->assertForbidden();
        }
        $this->assertSame(2, $draw->teamTies()->whereNull('published_at')->count());
    }

    public function test_super_user_cannot_bypass_locked_draw_guard(): void
    {
        $draw = $this->readyDraw();
        $draw->update(['published' => true, 'locked' => true]);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'))
            ->postJson(route('draw.enable-scoring', $draw))->assertUnprocessable();
        $this->assertSame(2, $draw->teamTies()->whereNull('published_at')->count());
    }

    public function test_missing_category_same_team_or_unselected_team_cannot_publish(): void
    {
        foreach (['category', 'same', 'selection'] as $case) {
            $draw = $this->readyDraw(1);
            $tie = $draw->teamTies()->first();
            if ($case === 'category') $tie->homeTeam->update(['category_event_id' => null]);
            if ($case === 'same') $tie->update(['away_team_id' => $tie->home_team_id]);
            if ($case === 'selection') $draw->teams_in_draw()->detach($tie->home_team_id);
            $this->postJson(route('draw.toggle.publish', $draw))->assertUnprocessable();
            $this->assertFalse((bool) $draw->fresh()->published);
            $this->assertNull($tie->fresh()->published_at);
        }
    }

    public function test_readiness_get_is_read_only_and_reports_orphan_matches_as_not_ready(): void
    {
        $draw = $this->readyDraw(1);
        app(DrawPublicationService::class)->publish($draw);
        $this->getJson(route('draw.scoring-readiness', $draw))->assertOk()->assertJsonPath('scoring.ready', true);
        $count = DrawAuditLog::count();
        TeamFixture::create(['draw_id' => $draw->id, 'round_nr' => 2, 'match_nr' => 2, 'rubber_code' => 'singles']);
        $this->getJson(route('draw.scoring-readiness', $draw))->assertOk()->assertJsonPath('scoring.ready', false);
        $this->assertSame($count, DrawAuditLog::count());
    }

    public function test_scoring_rules_without_operational_ties_cannot_advertise_legacy_readiness(): void
    {
        $draw = $this->readyDraw(1);
        $draw->fixtures()->update(['team_tie_id' => null]);
        $draw->teamTies()->delete();
        $draw->update(['team_format_snapshot' => null, 'team_scoring_rules' => ['sets_to_win' => 2]]);
        $this->getJson(route('draw.scoring-readiness', $draw))->assertOk()->assertJsonPath('scoring.ready', false);
        $this->postJson(route('draw.toggle.publish', $draw))->assertUnprocessable();
        $this->assertFalse((bool) $draw->fresh()->published);
    }
}
