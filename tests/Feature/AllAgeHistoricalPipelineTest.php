<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, CategoryEventRegistration, Draw, DrawSetting, Event, Fixture, FixtureResult, OrderOfPlay, Player, Registration, Venue};
use App\Services\Performance\{AllAgeRatingModel, PlayerAbilitySnapshotStore, PlayerRatingHistoryService, PlayerRatingHistoryStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, File};
use Tests\TestCase;

class AllAgeHistoricalPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function event(CarbonImmutable $date): array
    {
        $event = Event::factory()->create(['start_date' => $date->toDateString(), 'end_date' => $date->toDateString(), 'published' => true, 'results_published' => false]);
        $venue = Venue::forceCreate(['name' => 'Synthetic historical court']);
        DB::table('event_venue_courts')->insert(['event_id' => $event->id, 'venue_id' => $venue->id, 'label' => '1', 'ball_type' => 'yellow', 'active' => true]);
        return [$event, $venue];
    }

    private function match(Event $event, Venue $venue, Player $winner, Player $loser, string $label): Fixture
    {
        $field = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => $label])->id]);
        $registrations = [];
        foreach ([$winner, $loser] as $player) {
            $registration = Registration::factory()->create();
            $registration->players()->attach($player);
            CategoryEventRegistration::factory()->create(['category_event_id' => $field->id, 'registration_id' => $registration->id]);
            $registrations[] = $registration;
        }
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $field->id, 'drawName' => $label, 'published' => true]);
        DrawSetting::create(['draw_id' => $draw->id, 'workflow' => 'round_robin', 'score_format' => 'one_full_set', 'num_sets' => 1]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $registrations[0]->id, 'registration2_id' => $registrations[1]->id,
            'match_status' => 1, 'winner_registration' => $registrations[0]->id]);
        FixtureResult::create(['fixture_id' => $fixture->id, 'set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2]);
        OrderOfPlay::create(['fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id, 'court' => '1', 'time' => CarbonImmutable::parse($event->start_date)->format('Y-m-d').' 09:00:00']);
        return $fixture;
    }

    public function test_canonical_old_bridge_connects_recent_groups_with_low_saved_confidence_and_history_provenance(): void
    {
        $asOf = CarbonImmutable::parse('2026-10-29');
        $players = Player::factory()->count(5)->create();
        [$main, $mainVenue] = $this->event($asOf->subDays(9));
        $this->match($main, $mainVenue, $players[0], $players[1], 'U13 Boys A');
        $this->match($main, $mainVenue, $players[1], $players[2], 'U13 Boys A');
        [$island, $islandVenue] = $this->event($asOf->subDays(9));
        for ($i = 0; $i < 12; $i++) { $this->match($island, $islandVenue, $players[3], $players[4], 'U18 Boys A'); }
        [$older, $oldVenue] = $this->event($asOf->subDays(500));
        $bridge = $this->match($older, $oldVenue, $players[2], $players[3], 'U15 Boys A');
        $counts = [Player::count(), Fixture::count(), FixtureResult::count(), DB::table('user_players')->count()];
        $ability = app(PlayerSharedAbilityService::class);
        $baseline = $ability->validationSnapshot($asOf->addDay(), experiment: ['all_age_window_days' => 365]);
        $this->assertNull($baseline['all_age']['ratings'][$players[3]->id]['score']);
        $this->assertSame(365, $baseline['all_age']['policy']['window_days']);
        $snapshot = $ability->calculateSnapshot($asOf);
        $rating = $snapshot['all_age']['ratings'][$players[3]->id];
        $this->assertCount(1, $snapshot['all_age']['components']);
        $this->assertNotNull($rating['score']);
        $this->assertSame('historical', $rating['connection_status']);
        $this->assertSame(500, $rating['connection_age_days']);
        $this->assertSame($asOf->subDays(500)->toDateString(), $rating['connection_reference_date']);
        $this->assertSame('2026-10-20', $rating['last_direct_match']);
        $this->assertSame(39, $rating['confidence_index']);
        $this->assertSame('Low', $rating['confidence_label']);
        $store = app(PlayerAbilitySnapshotStore::class);
        $store->replace($snapshot, $asOf, $ability->fingerprint(), $store->manifest($snapshot));
        $headline = $ability->forPlayer($players[3])['headline'];
        $this->assertTrue($headline['historical_connection']);
        $this->assertSame('Low', $headline['confidence_label']);
        $this->assertStringContainsString('500 days old', $headline['confidence_explanation']);
        $this->assertSame($counts, [Player::count(), Fixture::count(), FixtureResult::count(), DB::table('user_players')->count()]);
        $historyStore = new class extends PlayerRatingHistoryStore {
            public function path(): string { return storage_path('framework/testing/all-age-historical-pipeline-'.getmypid().'.json'); }
        };
        try {
            $history = new PlayerRatingHistoryService($ability, $historyStore);
            $saved = $history->calculate($asOf);
            $latest = $saved['months'][11]['cohorts'][AllAgeRatingModel::COHORT][$players[3]->id];
            $this->assertTrue($latest['historical_connection']);
            $this->assertSame(500, $latest['connection_age_days']);
            $this->assertSame($rating['connection_reference_date'], $latest['connection_reference_date']);
            $historyStore->replace($saved, $ability->fingerprint());
            $point = $history->forPlayer($players[3], ['month' => 11])['point'];
            $this->assertSame('historical', $point['connection_status']);
            $bridgeEvidence = array_filter($saved['evidence'], fn ($row) => $row['source_id'] === 'match:'.$bridge->id && $row['cohort'] === AllAgeRatingModel::COHORT);
            $this->assertCount(1, $bridgeEvidence);
            DB::table('draws')->where('id', $bridge->draw_id)->update(['published' => false]);
            $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
            $this->assertNotNull($history->forPlayer($players[3])['reason']);
        } finally { File::delete($historyStore->path()); }
    }
}
