<?php
namespace Tests\Feature;

use App\Models\{Event, Draw, Player};
use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlayerAbilitySnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $date = '2026-10-07'): array
    {
        return ['cohorts' => [], 'names' => [], 'reason' => null, 'built_at' => $date.' 00:01:00 SAST', 'source_events' => Event::pluck('id')->all(), 'source_matches' => []];
    }
    private function save(string $date = '2026-10-07'): void
    {
        $store = new PlayerAbilitySnapshotStore;
        $store->replace($this->payload($date), CarbonImmutable::parse($date), 'test', $store->manifest($this->payload($date)));
    }
    public function test_missing_saved_snapshot_never_fits_on_read_or_check(): void
    {
        $this->partialMock(PlayerSharedAbilityService::class)->shouldNotReceive('calculateSnapshot', 'fingerprint');
        $player = Player::factory()->create();
        $this->assertNull(app(PlayerSharedAbilityService::class)->forPlayer($player)['headline']);
        $this->assertStringContainsString('pending', (new PlayerAbilitySnapshotStore)->current()['reason']);
        $this->artisan('player-ability:refresh --check')->assertFailed();
        $this->assertDatabaseCount('player_ability_snapshots', 0);
    }
    public function test_new_publications_wait_but_revocation_deletion_and_context_changes_withhold(): void
    {
        CarbonImmutable::setTestNow('2026-10-07');
        $event = Event::factory()->create(['published' => true, 'results_published' => false]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $this->save();
        Event::factory()->create(['published' => true]);
        DB::table('events')->where('id', $event->id)->update(['results_published' => true]);
        $this->assertNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        DB::table('draws')->where('id', $draw->id)->update(['drawName' => 'Changed category']);
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        DB::table('draws')->where('id', $draw->id)->update(['drawName' => $draw->drawName, 'published' => false]);
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        DB::table('draws')->where('id', $draw->id)->delete();
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        CarbonImmutable::setTestNow();
    }
    public function test_old_failed_and_dry_run_candidates_preserve_last_successful_payload(): void
    {
        $this->save();
        $saved = DB::table('player_ability_snapshots')->value('payload');
        $service = $this->partialMock(PlayerSharedAbilityService::class);
        $service->shouldReceive('fingerprint')->andReturn('unchanged');
        $service->shouldReceive('calculateSnapshot')->once()->andReturn($this->payload());
        $this->artisan('player-ability:refresh --dry-run')->assertSuccessful();
        $service->shouldReceive('calculateSnapshot')->once()->andThrow(new \RuntimeException('Private internal failure'));
        $this->artisan('player-ability:refresh')->assertFailed();
        $this->assertSame($saved, DB::table('player_ability_snapshots')->value('payload'));
        try { $this->save('2026-10-06'); $this->fail('Older snapshot replaced newer one'); } catch (\RuntimeException) {}
        $this->assertSame($saved, DB::table('player_ability_snapshots')->value('payload'));
    }
    public function test_stale_version_policy_and_future_snapshots_are_explicit(): void
    {
        CarbonImmutable::setTestNow('2026-10-08');
        $this->save();
        $this->assertTrue((new PlayerAbilitySnapshotStore)->current()['snapshot_stale']);
        DB::table('player_ability_snapshots')->update(['policy_hash' => 'old']);
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        $this->save();
        DB::table('player_ability_snapshots')->update(['as_of' => '2026-10-09']);
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        CarbonImmutable::setTestNow();
    }
    public function test_saved_payload_corruption_and_captured_category_edits_fail_closed(): void
    {
        $event = Event::factory()->create(['published' => true]);
        $category = \App\Models\Category::factory()->create(['name' => 'U10 Boys']);
        \App\Models\CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => $category->id]);
        $this->save();
        DB::table('categories')->where('id', $category->id)->update(['name' => 'U10 Girls']);
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        $this->save();
        DB::table('player_ability_snapshots')->update(['payload' => '{invalid']);
        $this->assertStringContainsString('unreadable', (new PlayerAbilitySnapshotStore)->current()['reason']);
        $this->save();
        DB::table('player_ability_snapshots')->update(['publication_manifest' => '{"draws":[{"wrong":1}]}']);
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
    }
    public function test_refresh_uses_same_manual_lock_and_rejects_nonconverged_replacement(): void
    {
        $this->save();
        $saved = DB::table('player_ability_snapshots')->value('payload');
        $lock = \Illuminate\Support\Facades\Cache::store('file')->lock('player-ability:refresh', 60);
        $this->assertTrue($lock->get());
        try { $this->artisan('player-ability:refresh')->assertFailed(); } finally { $lock->release(); }
        $payload = $this->payload();
        $payload['cohorts'] = ['u10 boys' => ['components' => ['bad' => ['converged' => false]]]];
        try {
            $store = new PlayerAbilitySnapshotStore;
            $store->replace($payload, CarbonImmutable::parse('2026-10-07'), 'test', $store->manifest($payload));
            $this->fail('Nonconverged result replaced saved snapshot');
        } catch (\RuntimeException) {}
        $this->assertSame($saved, DB::table('player_ability_snapshots')->value('payload'));
    }
    public function test_scheduler_explicitly_uses_midnight_south_african_time_and_overlap_lock(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $events = collect($schedule->events())->filter(fn ($event) => str_contains($event->command ?? '', 'player-ability:refresh'));
        $this->assertCount(2, $events);
        $event = $events->first(fn ($event) => !str_contains($event->command, '--pending'));
        $this->assertSame('0 0 * * *', $event->expression);
        $this->assertSame('Africa/Johannesburg', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $background = $events->first(fn ($event) => str_contains($event->command, '--pending'));
        $this->assertSame('* * * * *', $background->expression);
        $this->assertTrue($background->withoutOverlapping);
        $this->assertTrue($background->runInBackground);
    }    public function test_new_member_or_duplicate_category_context_withholds_existing_snapshot(): void
    {
        $event = Event::factory()->create(['published' => true]);
        $field = \App\Models\CategoryEvent::factory()->create(['event_id' => $event->id]);
        $registration = \App\Models\Registration::factory()->create();
        $player = Player::factory()->create();
        $registration->players()->attach($player);
        \App\Models\CategoryEventRegistration::factory()->create(['category_event_id' => $field->id, 'registration_id' => $registration->id]);
        $this->save();
        $registration->players()->attach(Player::factory()->create());
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        $this->save();
        \App\Models\CategoryEventRegistration::factory()->create(['category_event_id' => $field->id]);
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        $this->save();
        \App\Models\CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => $field->category_id]);
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
    }
    public function test_raw_source_context_edit_during_calculation_keeps_last_good(): void
    {
        $event = Event::factory()->create(['published' => true]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $this->save();
        $saved = DB::table('player_ability_snapshots')->value('payload');
        $this->partialMock(PlayerSharedAbilityService::class)->shouldReceive('calculateSnapshot')->once()->andReturnUsing(function () use ($draw) {
            DB::table('draws')->where('id', $draw->id)->update(['team_draw_selection' => '{"changed":true}']);
            return $this->payload();
        });
        $this->artisan('player-ability:refresh')->assertFailed();
        $this->assertSame($saved, DB::table('player_ability_snapshots')->value('payload'));
    }}
