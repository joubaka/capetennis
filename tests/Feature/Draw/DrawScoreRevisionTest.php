<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, DrawAuditLog, Event, Fixture, OrderOfPlay, Registration, TeamFixture, User, Venue};
use App\Services\Draw\IndividualScoreRevision;
use App\Services\TeamFixtureScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DrawScoreRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function match(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $draw = Draw::factory()->create(['event_id' => Event::factory()->create()->id, 'locked' => false, 'published' => false]);
        $draw->settings()->create(['workflow' => 'round_robin', 'num_sets' => 1, 'score_format' => 'one_full_set']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $draw->event_id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $players = Registration::factory()->count(2)->create();
        $group = $draw->groups()->create(['name' => 'A']);
        $group->registrations()->sync($players->modelKeys());
        $draw->registrations()->sync($players->modelKeys());
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id, 'stage' => 'RR',
            'match_status' => 0, 'winner_registration' => null, 'registration1_id' => $players[0]->id, 'registration2_id' => $players[1]->id]);
        return [$draw, $fixture];
    }

    public static function clients(): array
    {
        return ['admin' => [false], 'venue api' => [true]];
    }

    #[DataProvider('clients')]
    public function test_missing_and_stale_save_or_delete_cannot_change_results_or_audit(bool $api): void
    {
        [$draw, $fixture] = $this->match();
        $save = $api ? route('api.draws.fixtures.score.store', [$draw, $fixture]) : route('backend.roundrobin.score.store', $fixture);
        $delete = $api ? route('api.draws.fixtures.score.delete', [$draw, $fixture]) : route('backend.roundrobin.score.delete', $fixture);
        $revision = app(IndividualScoreRevision::class)->forFixture($fixture->fresh());
        $this->postJson($save, ['sets' => ['6-2']])->assertConflict();
        $this->postJson($save, ['sets' => ['6-2'], 'score_revision' => $revision])->assertOk();
        $auditCount = DrawAuditLog::count();
        $rows = $fixture->fixtureResults()->get()->toArray();
        $this->postJson($save, ['sets' => ['2-6'], 'score_revision' => $revision])->assertConflict();
        $this->postJson($save, ['sets' => ['6-3'], 'score_revision' => $revision])->assertConflict();
        $this->deleteJson($delete, ['score_revision' => $revision])->assertConflict();
        $this->deleteJson($delete)->assertConflict();
        $this->assertSame($rows, $fixture->fixtureResults()->get()->toArray());
        $this->assertSame($auditCount, DrawAuditLog::count());
        $this->deleteJson($delete, ['score_revision' => app(IndividualScoreRevision::class)->forFixture($fixture->fresh())])->assertOk();
        $this->assertSame(0, $fixture->fixtureResults()->count());
    }

    public function test_participant_and_rule_changes_invalidate_an_open_score_form(): void
    {
        [$draw, $fixture] = $this->match();
        $url = route('api.draws.fixtures.score.store', [$draw, $fixture]);
        $revision = app(IndividualScoreRevision::class)->forFixture($fixture->fresh());
        $fixture->update(['registration2_id' => Registration::factory()->create()->id]);
        $this->postJson($url, ['sets' => ['6-2'], 'score_revision' => $revision])->assertConflict();
        $revision = app(IndividualScoreRevision::class)->forFixture($fixture->fresh());
        $draw->settings()->update(['score_format' => 'best_of_3_full']);
        $this->postJson($url, ['sets' => ['6-2'], 'score_revision' => $revision])->assertConflict();
        $this->assertDatabaseCount('fixture_results', 0);
        $this->actingAs(User::factory()->create())->postJson($url, ['sets' => ['6-2'], 'score_revision' => $revision])->assertForbidden();
    }

    public function test_foreign_draw_cannot_use_a_valid_fixture_token(): void
    {
        [$draw, $fixture] = $this->match();
        $other = Draw::factory()->create(['event_id' => $draw->event_id]);
        $this->postJson(route('api.draws.fixtures.score.store', [$other, $fixture]), [
            'sets' => ['6-2'], 'score_revision' => app(IndividualScoreRevision::class)->forFixture($fixture->fresh()),
        ])->assertUnprocessable();
        $this->assertDatabaseCount('fixture_results', 0);
    }

    public function test_published_timed_projection_token_matches_unchanged_working_booking(): void
    {
        [$draw, $fixture] = $this->match();
        $draw->settings()->update(['score_format' => 'timed', 'match_duration_minutes' => 20, 'changeover_minutes' => 5]);
        $venue = Venue::forceCreate(['name' => 'Revision test venue']);
        $booking = OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id,
            'time' => '2026-11-15 08:00:00', 'court' => '1', 'duration_minutes' => 20, 'gap_minutes' => 5]);
        $projected = $fixture->fresh();
        $projected->setRelation('orderOfPlay', new OrderOfPlay(['time' => $booking->time, 'court' => '1', 'venue_id' => $venue->id, 'duration_minutes' => 20]));
        $revision = app(IndividualScoreRevision::class)->forFixture($projected);
        $this->postJson(route('api.draws.fixtures.score.store', [$draw, $fixture]), ['sets' => ['4-4'], 'score_revision' => $revision])->assertOk();
    }

    public function test_team_score_content_revision_rejects_overwrite_and_delete_with_same_participants(): void
    {
        // Direct service test isolates result concurrency from roster and UI setup.
        $draw = Draw::factory()->create(['event_id' => Event::factory()->create()->id, 'locked' => false]);
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1, 'match_nr' => 1, 'fixture_type' => 1, 'match_status' => 0]);
        $service = app(TeamFixtureScoreService::class);
        $revision = $service->revision($fixture->fresh());
        $service->save($fixture, ['set1_home' => 6, 'set1_away' => 2, 'score_revision' => $revision]);
        foreach (['save', 'delete', 'missing'] as $operation) {
            try {
                if ($operation === 'save') $service->save($fixture->fresh(), ['set1_home' => 2, 'set1_away' => 6, 'score_revision' => $revision]);
                else $service->delete($fixture->fresh(), $operation === 'missing' ? null : $revision);
                $this->fail('A stale or absent team revision must be rejected.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(409, $exception->getStatusCode());
            }
        }
        $this->assertDatabaseHas('team_fixture_results', ['team_fixture_id' => $fixture->id, 'team1_score' => 6, 'team2_score' => 2]);
        $service->delete($fixture->fresh(), $service->revision($fixture->fresh()));
        $this->assertSame(0, $fixture->teamResults()->count());
    }

    public function test_rebuild_without_reviewed_assignment_revision_preserves_fixtures(): void
    {
        [$draw, $fixture] = $this->match();
        $this->postJson(route('backend.draw.regenerate-rr', $draw))->assertUnprocessable();
        $this->assertDatabaseHas('fixtures', ['id' => $fixture->id]);
        $this->assertDatabaseCount('draw_audit_logs', 0);
    }

    public function test_team_http_save_and_clear_require_reviewed_score_content(): void
    {
        [$draw] = $this->match();
        DB::table('eventtypes')->updateOrInsert(['id' => 990], ['name' => 'Score revision team event', 'type' => \App\Models\EventType::TEAM]);
        $draw->event->update(['eventType' => 990]);
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1, 'match_nr' => 1, 'fixture_type' => 1, 'match_status' => 0]);
        $service = app(TeamFixtureScoreService::class);
        $save = route('frontend.fixtures.score.store', $fixture->id);
        $delete = route('frontend.fixtures.score.delete', $fixture->id);
        $scores = ['set1_home' => 6, 'set1_away' => 2];
        $revision = $service->revision($fixture->fresh());
        $this->postJson($save, $scores)->assertConflict();
        $this->postJson($save, $scores + ['score_revision' => $revision])->assertOk();
        $auditCount = DrawAuditLog::count();
        $this->postJson($save, ['set1_home' => 6, 'set1_away' => 3, 'score_revision' => $revision])->assertConflict();
        $this->deleteJson($delete, ['score_revision' => $revision])->assertConflict();
        $this->assertSame($auditCount, DrawAuditLog::count());
        $this->assertDatabaseHas('team_fixture_results', ['team_fixture_id' => $fixture->id, 'team2_score' => 2]);
        $this->deleteJson($delete, ['score_revision' => $service->revision($fixture->fresh())])->assertOk();
        $this->assertSame(0, $fixture->teamResults()->count());
    }

    public function test_rebuild_rejects_newer_bookings_even_when_players_are_unchanged(): void
    {
        [$draw, $fixture] = $this->match();
        $revision = app(\App\Services\Draw\RoundRobinRebuildService::class)->revision($draw);
        $venue = Venue::forceCreate(['name' => 'New booking after review']);
        $booking = OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id,
            'time' => '2026-11-15 08:00:00', 'court' => '1', 'duration_minutes' => 30]);
        $this->postJson(route('backend.draw.regenerate-rr', $draw), [
            'revision' => app(\App\Services\Draw\GroupAssignmentService::class)->revision($draw),
            'rebuild_revision' => $revision,
        ])->assertConflict();
        $this->assertDatabaseHas('fixtures', ['id' => $fixture->id]);
        $this->assertDatabaseHas('order_of_plays', ['id' => $booking->id]);
        $this->assertDatabaseCount('draw_audit_logs', 0);
    }

    public function test_group_save_cannot_omit_the_reviewed_assignment_revision(): void
    {
        [$draw, $fixture] = $this->match();
        $group = $draw->groups()->first();
        $before = $group->groupRegistrations()->pluck('registration_id')->all();
        foreach ([route('backend.draw.save-groups', $draw), route('api.draws.groups.save', $draw)] as $url) {
            $this->postJson($url, ['groups' => [['group_id' => $group->id, 'registration_ids' => []]]])->assertUnprocessable();
        }
        $this->assertSame($before, $group->groupRegistrations()->pluck('registration_id')->all());
        $this->assertDatabaseHas('fixtures', ['id' => $fixture->id]);
        $this->assertDatabaseCount('draw_audit_logs', 0);
    }
}
