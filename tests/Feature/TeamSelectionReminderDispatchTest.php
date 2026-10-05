<?php

namespace Tests\Feature;

use App\Models\{BulkEmailLog, CategoryEvent, Event, EventRegion, Player, Team, TeamRegion, TeamSelectionImport, TeamSelectionInvitation, User};
use App\Services\BulkMailDispatcher;
use App\Services\TeamSelection\TeamSelectionReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamSelectionReminderDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(int $count = 1): array
    {
        Queue::fake();
        $event = Event::factory()->create();
        $actor = User::factory()->create();
        $region = TeamRegion::create(['region_name' => 'Reminder region']);
        $eventRegion = EventRegion::forceCreate(['event_id' => $event->id, 'region_id' => $region->id]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $region->id]);
        $import = TeamSelectionImport::create(['source_id' => 1, 'event_id' => $event->id, 'region_id' => $region->id, 'series_id' => 1,
            'ranking_run_id' => (string) Str::uuid(), 'imported_by' => $actor->id, 'status' => 'sent']);
        for ($index = 1; $index <= $count; $index++) {
            $player = Player::factory()->create(['email' => "reminder{$index}@example.test", 'userId' => null]);
            TeamSelectionInvitation::create(['import_id' => $import->id, 'event_id' => $event->id, 'region_id' => $region->id,
                'team_id' => $team->id, 'player_id' => $player->id, 'ranking_list_id' => 1, 'ranking_position' => $index,
                'queue_position' => $index, 'status' => TeamSelectionInvitation::INVITED]);
        }
        return [$event, $eventRegion, $actor];
    }

    public function test_repeated_reminder_campaign_reports_zero_and_keeps_an_exclusion_record(): void
    {
        [$event, $region, $actor] = $this->fixture();
        $service = app(TeamSelectionReminderService::class);
        $token = (string) Str::uuid();
        $hash = $service->recipientHash($event, $region, 'registration_clothing', 'all');
        $first = $service->send($event, $region, 'registration_clothing', 'all', $token, $hash, $actor);
        $repeat = $service->send($event, $region, 'registration_clothing', 'all', $token, $hash, $actor);
        $this->assertSame(1, $first['queued']);
        $this->assertSame('success', $first['severity']);
        $this->assertSame(0, $repeat['queued']);
        $this->assertSame(0, $repeat['players']);
        $this->assertSame(1, $repeat['skipped']);
        $this->assertSame('error', $repeat['severity']);
        $this->assertSame(1, BulkEmailLog::where('status', 'queued')->count());
        $this->assertSame(1, BulkEmailLog::where('status', 'skipped')->count());
    }

    public function test_partial_queue_failure_is_counted_and_returns_warning(): void
    {
        [$event, $region, $actor] = $this->fixture(2);
        $this->mock(BulkMailDispatcher::class)->shouldReceive('dispatch')->twice()->andReturn(
            ['total' => 1, 'queued' => 0, 'skipped' => 0, 'failed' => 1, 'invalid' => 0, 'duplicate' => 0],
            ['total' => 1, 'queued' => 1, 'skipped' => 0, 'failed' => 0, 'invalid' => 0, 'duplicate' => 0],
        );
        $service = app(TeamSelectionReminderService::class);
        $stats = $service->send($event, $region, 'registration_clothing', 'all', (string) Str::uuid(),
            $service->recipientHash($event, $region, 'registration_clothing', 'all'), $actor);
        $this->assertSame(1, $stats['queued']);
        $this->assertSame(1, $stats['failed']);
        $this->assertSame(1, $stats['players']);
        $this->assertSame('warning', $stats['severity']);
    }
}
