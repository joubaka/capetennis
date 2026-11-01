<?php

namespace Tests\Feature;

use App\Models\{Player, PlayerPathwayAchievement, PlayerPathwayProfile, SiteSetting, User};
use App\Services\Performance\{PlayerPathwayService, PlayerRatingAccess, PlayerRatingHistoryService};
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Gate, Schema};
use Mockery;
use Tests\TestCase;

class PlayerPathwayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Schema::create('players', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('userId')->nullable(); $table->string('name'); $table->string('surname');
        });
        Schema::create('user_players', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id'); $table->unsignedBigInteger('player_id');
        });
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id(); $table->string('key'); $table->string('value'); $table->string('group')->nullable(); $table->string('label')->nullable(); $table->timestamps();
        });
        (require database_path('migrations/2026_11_01_160000_create_player_pathway_tables.php'))->up();
        DB::table('players')->insert([
            ['id' => 1, 'userId' => 10, 'name' => 'Fictional', 'surname' => 'Player'],
            ['id' => 2, 'userId' => 20, 'name' => 'Other', 'surname' => 'Player'],
        ]);
        // Other operational middleware owns unrelated checkout/audit state and is covered separately.
        $this->withoutMiddleware([
            \App\Http\Middleware\ExpireUnpaidEventCheckout::class,
            \App\Http\Middleware\RecordAuditRequest::class,
            \App\Http\Middleware\AuditJtaIntegrationAccess::class,
        ]);
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->andReturn([
            'reason' => 'No saved history yet.', 'series' => [], 'points' => [],
        ]);
        $this->app->instance(\App\Services\Performance\PendingBankRefundCount::class, new class {
            public function count(): int { return 0; }
        });
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function actor(int $id = 99, bool $admin = true): User
    {
        $actor = Mockery::mock(User::class)->makePartial();
        $actor->id = $id;
        $actor->shouldReceive('hasRole')->with('super-user')->andReturn($admin);
        $actor->shouldReceive('hasAnyRole')->andReturn($admin);
        $actor->shouldReceive('checkPermissionTo')->andReturn(false);
        $actor->shouldReceive('getForeignKey')->andReturn('user_id');
        return $actor;
    }

    private function metrics(float $score = 60, array $stages = ['red' => 3, 'orange' => 0, 'standard' => 0]): array
    {
        return ['player_id' => 1, 'available' => true, 'score' => $score, 'events' => max($stages),
            'stage_events' => $stages, 'unknown_stage_events' => 0, 'date' => '2026-11-01',
            'comparable' => true, 'stale' => false, 'reason' => null];
    }

    public function test_initial_targets_have_exact_boundaries_and_require_three_own_stage_events(): void
    {
        $service = app(PlayerPathwayService::class); $player = Player::findOrFail(1); $actor = $this->actor();
        foreach ([['red', null, 50], ['orange', null, 60], ['standard', 'B', 70], ['standard', 'A', 85]] as [$stage, $division, $target]) {
            $service->recordStage($player, $actor, ['stage' => $stage, 'wilson_division' => $division]);
            $counts = ['red' => 0, 'orange' => 0, 'standard' => 0]; $counts[$stage] = 3;
            $summary = $service->summary($player, ['pathway_metrics' => $this->metrics($target, $counts)]);
            $this->assertTrue($summary['ready']);
            $this->assertSame((float) $target, $summary['target']['ctr']);
            $this->assertFalse($service->summary($player, ['pathway_metrics' => $this->metrics($target - .1, $counts)])['ready']);
            $counts[$stage] = 2;
            $this->assertFalse($service->summary($player, ['pathway_metrics' => $this->metrics($target, $counts)])['ready']);
        }
        $this->assertDatabaseCount('player_pathway_achievements', 0);
        $this->assertDatabaseHas('player_pathway_profiles', ['player_id' => 1, 'stage' => 'standard', 'wilson_division' => 'A']);
    }

    public function test_starting_orange_or_skipping_to_standard_never_completes_earlier_stages(): void
    {
        $service = app(PlayerPathwayService::class); $player = Player::findOrFail(1); $actor = $this->actor();
        $service->recordStage($player, $actor, ['stage' => 'orange']);
        $summary = $service->summary($player, ['pathway_metrics' => $this->metrics(99, ['red' => 0, 'orange' => 3, 'standard' => 0])]);
        $this->assertTrue($summary['ready']);
        $this->assertFalse($summary['stageProgress']['red']['met']);
        $this->assertTrue($summary['stageProgress']['orange']['met']);
        $service->recordStage($player, $actor, ['stage' => 'standard', 'wilson_division' => 'B']);
        $summary = $service->summary($player, ['pathway_metrics' => $this->metrics(99, ['red' => 0, 'orange' => 0, 'standard' => 3])]);
        $this->assertFalse($summary['stageProgress']['red']['met']);
        $this->assertFalse($summary['stageProgress']['orange']['met']);
        $this->assertDatabaseCount('player_pathway_achievements', 0);
    }

    public function test_unknown_stage_missing_targets_or_unclassified_events_never_award_readiness(): void
    {
        $service = app(PlayerPathwayService::class); $player = Player::findOrFail(1);
        $history = ['pathway_metrics' => $this->metrics(99)];
        $this->assertFalse($service->summary($player, $history)['ready']);
        $this->assertSame(3, $service->summary($player, $history)['events']);
        $service->recordStage($player, $this->actor(), ['stage' => 'red']);
        $unclassified = $this->metrics(99, ['red' => 0, 'orange' => 0, 'standard' => 0]);
        $unclassified['events'] = 3; $unclassified['unknown_stage_events'] = 3;
        $summary = $service->summary($player, ['pathway_metrics' => $unclassified]);
        $this->assertFalse($summary['ready']);
        $this->assertStringContainsString('lack confirmed ball metadata', $summary['reason']);
        config(['pathway.targets.red' => ['ctr' => null, 'events' => 3]]);
        $summary = $service->summary($player, $history);
        $this->assertFalse($summary['ready']);
        $this->assertNull($summary['target']);
        $this->assertStringContainsString('not configured', $summary['reason']);
    }

    public function test_stale_nonfinite_uncomparable_withheld_or_other_player_metrics_fail_closed(): void
    {
        $service = app(PlayerPathwayService::class); $player = Player::findOrFail(1);
        $service->recordStage($player, $this->actor(), ['stage' => 'red']);
        foreach ([['stale' => true], ['comparable' => false], ['score' => INF], ['score' => NAN], ['score' => 101], ['player_id' => 2], ['available' => false]] as $change) {
            $this->assertFalse($service->summary($player, ['pathway_metrics' => array_replace($this->metrics(99), $change)])['ready']);
        }
        $this->assertFalse($service->summary($player, ['reason' => 'Publication revoked', 'pathway_metrics' => $this->metrics(99)])['ready']);
        $this->assertDatabaseCount('player_pathway_achievements', 0);
    }

    public function test_manual_verification_is_gone_and_existing_audit_records_are_not_progress(): void
    {
        $this->actingAs($this->actor());
        $service = app(PlayerPathwayService::class); $player = Player::findOrFail(1);
        $service->recordStage($player, $this->actor(), ['stage' => 'red']);
        $audit = PlayerPathwayAchievement::create(['player_id' => 1, 'stage' => 'red', 'milestone' => 'coach_review', 'verified_by_user_id' => 9, 'verified_at' => now()])->fresh()->getAttributes();
        $this->postJson(route('my.tennis.pathway.milestones', 1), ['stage' => 'red', 'milestones' => ['independent_scoring']])->assertStatus(410);
        $this->assertSame($audit, PlayerPathwayAchievement::firstOrFail()->getAttributes());
        $this->assertFalse($service->summary($player)['ready']);
        $this->get(route('my.tennis.rating', 1))->assertOk()->assertSee('Automatic progress')->assertDontSee('coach_review')->assertDontSee('Verify selected milestones');
        $this->assertDatabaseCount('player_pathway_achievements', 1);
    }

    public function test_linked_entitled_viewer_can_read_but_cannot_write_or_read_another_player(): void
    {
        SiteSetting::set(PlayerRatingAccess::SETTING, '1'); Gate::define('player-premium-rating', fn () => true);
        DB::table('user_players')->insert(['user_id' => 11, 'player_id' => 1]);
        $this->actingAs($this->actor(11, false));
        $this->get(route('my.tennis.rating', 1))->assertOk()->assertSee('Your tennis pathway')->assertDontSee('data-pathway-form', false);
        $this->get(route('my.tennis.rating', 2))->assertForbidden();
        $this->postJson(route('my.tennis.pathway.stage', 1), ['stage' => 'standard'])->assertForbidden();
        $this->postJson(route('my.tennis.pathway.milestones', 1), [])->assertForbidden();
        $this->assertDatabaseCount('player_pathway_profiles', 0);
    }

    public function test_recommendations_branch_after_red_without_entry_or_stage_changes(): void
    {
        $service = app(PlayerPathwayService::class); $player = Player::findOrFail(1);
        $service->recordStage($player, $this->actor(), ['stage' => 'red']);
        $summary = $service->summary($player, ['pathway_metrics' => $this->metrics(50)]);
        $this->assertTrue($summary['ready']); $this->assertSame('Wilson Red', $summary['competition']);
        $this->assertContains('Wilson Orange', $summary['options']);
        $this->assertStringContainsString('Wilson U10 B', $summary['options'][1]);
        $this->assertDatabaseHas('player_pathway_profiles', ['player_id' => 1, 'stage' => 'red']);
        $this->assertDatabaseCount('player_pathway_achievements', 0);
    }

    public function test_stage_recording_validates_divisions_preserves_audit_and_does_not_accept_spoofed_player(): void
    {
        $this->actingAs($this->actor());
        $this->postJson(route('my.tennis.pathway.stage', 1), ['stage' => 'unknown'])->assertUnprocessable();
        $this->postJson(route('my.tennis.pathway.stage', 1), ['stage' => 'red', 'wilson_division' => 'A'])->assertUnprocessable();
        $this->postJson(route('my.tennis.pathway.stage', 1), ['stage' => 'green', 'player_id' => 2])->assertOk();
        $this->assertDatabaseHas('player_pathway_profiles', ['player_id' => 1, 'stage' => 'standard', 'wilson_division' => null, 'recorded_by_user_id' => 99]);
        $this->assertDatabaseMissing('player_pathway_profiles', ['player_id' => 2]);
        $this->assertNull(app(PlayerPathwayService::class)->summary(Player::findOrFail(1))['target']);
        Schema::drop('player_pathway_profiles');
        $this->get(route('my.tennis.rating', 1))->assertOk()->assertSee('awaiting setup');
        $this->postJson(route('my.tennis.pathway.stage', 1), ['stage' => 'red'])->assertStatus(503);
    }

    public function test_guest_routes_and_direct_service_writes_remain_private(): void
    {
        $this->getJson(route('my.tennis.rating', 1))->assertUnauthorized();
        $this->postJson(route('my.tennis.pathway.stage', 1), ['stage' => 'red'])->assertUnauthorized();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PlayerPathwayService::class)->recordStage(Player::findOrFail(1), $this->actor(10, false), ['stage' => 'red']);
    }
}