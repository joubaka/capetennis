<?php

namespace Tests\Feature;

use App\Models\{Draw, Event, Fixture, OrderOfPlay, TeamFixture};
use App\Services\Performance\{AllAgeMatchFormatResolver, PlayerAbilitySnapshotStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AllAgeRatingSourceContextTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $event = Event::factory()->create(['published' => true]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id]);
        $team = TeamFixture::create(['draw_id' => $draw->id, 'match_nr' => 1, 'fixture_type' => 1]);
        $payload = ['cohorts' => [], 'all_age' => ['ratings' => [], 'components' => [], 'players' => [], 'reference_component' => null], 'names' => [], 'reason' => null,
            'built_at' => '2026-10-01 00:01:00 SAST', 'source_events' => [$event->id], 'source_matches' => ['match:'.$fixture->id, 'team-match:'.$team->id]];
        return compact('event', 'draw', 'fixture', 'team', 'payload');
    }

    public function test_first_court_declaration_in_previously_empty_event_withholds_saved_assumptions(): void
    {
        ['event' => $event, 'payload' => $payload] = $this->context();
        $store = new PlayerAbilitySnapshotStore;
        $manifest = $store->manifest($payload);
        $store->replace($payload, CarbonImmutable::parse('2026-10-01'), 'test', $manifest);
        $this->assertNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        DB::table('event_venue_courts')->insert(['event_id' => $event->id, 'venue_id' => 1, 'label' => '1', 'ball_type' => 'red', 'active' => true]);
        $this->assertFalse($store->published($manifest));
        $this->assertStringContainsString('withheld', (new PlayerAbilitySnapshotStore)->current()['reason']);
    }

    public function test_ball_changes_and_new_draw_allocations_invalidate_exact_format_manifest(): void
    {
        ['event' => $event, 'draw' => $draw, 'payload' => $payload] = $this->context();
        $id = DB::table('event_venue_courts')->insertGetId(['event_id' => $event->id, 'venue_id' => 1, 'label' => '1', 'ball_type' => 'yellow', 'active' => true]);
        $store = new PlayerAbilitySnapshotStore;
        $manifest = $store->manifest($payload);
        $fingerprint = app(PlayerSharedAbilityService::class)->fingerprint();
        DB::table('event_venue_courts')->where('id', $id)->update(['ball_type' => 'green']);
        $this->assertFalse($store->published($manifest));
        $this->assertNotSame($fingerprint, app(PlayerSharedAbilityService::class)->fingerprint());
        DB::table('event_venue_courts')->where('id', $id)->update(['ball_type' => 'yellow']);
        $this->assertTrue($store->published($manifest));
        DB::table('draw_venue_court_allocations')->insert(['draw_id' => $draw->id, 'venue_id' => 1, 'court_label' => '1']);
        $this->assertFalse($store->published($manifest));
    }

    public function test_individual_schedule_additions_and_court_changes_invalidate_saved_format(): void
    {
        ['draw' => $draw, 'fixture' => $fixture, 'payload' => $payload] = $this->context();
        $store = new PlayerAbilitySnapshotStore;
        $empty = $store->manifest($payload);
        $schedule = OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => 1, 'court' => '1', 'time' => '2026-10-01 09:00:00']);
        $this->assertFalse($store->published($empty));
        $manifest = $store->manifest($payload);
        DB::table('order_of_plays')->where('id', $schedule->id)->update(['court' => '2']);
        $this->assertFalse($store->published($manifest));
    }

    public function test_team_venue_court_and_schedule_are_immutable_snapshot_dependencies(): void
    {
        ['team' => $team, 'payload' => $payload] = $this->context();
        $store = new PlayerAbilitySnapshotStore;
        foreach (['venue_id' => 2, 'court_label' => '3', 'scheduled_at' => '2026-10-01 10:00:00'] as $column => $value) {
            $manifest = $store->manifest($payload);
            $fingerprint = app(PlayerSharedAbilityService::class)->fingerprint();
            DB::table('team_fixtures')->where('id', $team->id)->update([$column => $value]);
            $this->assertFalse($store->published($manifest));
            $this->assertNotSame($fingerprint, app(PlayerSharedAbilityService::class)->fingerprint());
        }
    }

    public function test_resolver_uses_exact_event_court_and_refuses_unresolved_mixed_draw(): void
    {
        ['event' => $event, 'draw' => $draw] = $this->context();
        foreach (['1' => 'yellow', '2' => 'green'] as $label => $ball) {
            DB::table('event_venue_courts')->insert(['event_id' => $event->id, 'venue_id' => 1, 'label' => (string) $label, 'ball_type' => $ball, 'active' => true]);
            DB::table('draw_venue_court_allocations')->insert(['draw_id' => $draw->id, 'venue_id' => 1, 'court_label' => (string) $label]);
        }
        $resolver = new AllAgeMatchFormatResolver;
        $this->assertSame('yellow', $resolver->resolve($event->id, ['draw_id' => $draw->id, 'venue_id' => 1, 'court_label' => '1'])['ball_type']);
        $this->assertNotNull($resolver->resolve($event->id, ['draw_id' => $draw->id])['format_reason']);
        $other = Event::factory()->create(['published' => true]);
        $this->assertNull($resolver->resolve($other->id, ['venue_id' => 1, 'court_label' => '2'])['ball_type']);
    }
}
