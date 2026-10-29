<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, CategoryEventRegistration, Draw, DrawSetting, Event, Fixture, FixtureResult, OrderOfPlay, Player, Registration, User, Venue};
use App\Services\Performance\{AllAgeRatingModel, PlayerAbilitySnapshotStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AllAgeRatingPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function match(Event $event, Venue $venue, string $label, Player $winner, Player $loser, bool $published = true): Fixture
    {
        $field = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => $label])->id]);
        $registrations = [];
        foreach ([$winner, $loser] as $player) {
            $registration = Registration::factory()->create();
            $registration->players()->attach($player);
            CategoryEventRegistration::factory()->create(['category_event_id' => $field->id, 'registration_id' => $registration->id]);
            $registrations[] = $registration;
        }
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $field->id, 'drawName' => $label, 'published' => $published]);
        DrawSetting::create(['draw_id' => $draw->id, 'workflow' => 'round_robin', 'score_format' => 'one_full_set', 'num_sets' => 1]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $registrations[0]->id, 'registration2_id' => $registrations[1]->id, 'match_status' => 1, 'winner_registration' => $registrations[0]->id]);
        FixtureResult::create(['fixture_id' => $fixture->id, 'set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2]);
        OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id, 'court' => '1', 'time' => '2026-10-01 09:00:00']);
        return $fixture;
    }

    public function test_published_played_results_build_one_saved_cross_age_rating_and_metadata_changes_withhold_it(): void
    {
        $event = Event::factory()->create(['start_date' => '2026-10-01', 'end_date' => '2026-10-02', 'published' => true, 'results_published' => true]);
        $venue = Venue::forceCreate(['name' => 'Synthetic shared court']);
        DB::table('event_venue_courts')->insert(['event_id' => $event->id, 'venue_id' => $venue->id, 'label' => '1', 'ball_type' => 'yellow', 'active' => true]);
        $players = Player::factory()->count(6)->create();
        $this->match($event, $venue, 'U10 Boys A', $players[0], $players[1]);
        $this->match($event, $venue, 'U13 Boys A', $players[1], $players[2]);
        $this->match($event, $venue, 'U18 Boys A', $players[2], $players[3]);
        $this->match($event, $venue, 'U16 Boys A', $players[4], $players[5], false);
        $before = [Player::count(), Fixture::count(), FixtureResult::count(), DB::table('user_players')->count()];
        $ability = app(PlayerSharedAbilityService::class);
        $asOf = CarbonImmutable::parse('2026-10-03');
        $snapshot = $ability->calculateSnapshot($asOf);
        $this->assertNull($snapshot['reason']);
        $this->assertCount(4, $snapshot['all_age']['ratings']);
        $this->assertCount(1, $snapshot['all_age']['components']);
        $this->assertSame(['u10 boys', 'u13 boys', 'u18 boys'], $snapshot['all_age']['ratings'][$players[0]->id]['component_age_groups']);
        $this->assertGreaterThan($snapshot['all_age']['ratings'][$players[3]->id]['score'], $snapshot['all_age']['ratings'][$players[0]->id]['score']);
        $this->assertSame($before, [Player::count(), Fixture::count(), FixtureResult::count(), DB::table('user_players')->count()]);
        $store = app(PlayerAbilitySnapshotStore::class);
        $store->replace($snapshot, $asOf, $ability->fingerprint(), $store->manifest($snapshot));
        $this->assertSame(AllAgeRatingModel::COHORT, $ability->forPlayer($players[0])['headline']['cohort']);
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $badges = $ability->allAgeBadgeSnapshot();
        $this->assertSame($snapshot['all_age']['ratings'][$players[0]->id]['score'], $badges[$players[0]->id]['score']);
        $this->assertArrayNotHasKey('local_score', $badges[$players[0]->id]);
        $this->assertArrayNotHasKey('strength', $badges[$players[0]->id]);
        DB::table('event_venue_courts')->where('event_id', $event->id)->update(['ball_type' => 'green']);
        $this->assertStringContainsString('withheld', (new PlayerAbilitySnapshotStore)->current()['reason']);
        $rebuilt = $ability->calculateSnapshot($asOf);
        $this->assertSame([], $rebuilt['all_age']['ratings']);
    }

    public function test_corrupt_saved_shared_reference_and_nonconverged_components_are_withheld_on_read(): void
    {
        $event = Event::factory()->create(['start_date' => '2026-10-01', 'end_date' => '2026-10-02', 'published' => true]);
        $venue = Venue::forceCreate(['name' => 'Synthetic standard court']);
        $players = Player::factory()->count(2)->create();
        $this->match($event, $venue, 'U13 Boys A', $players[0], $players[1]);
        $service = app(PlayerSharedAbilityService::class);
        $snapshot = $service->calculateSnapshot(CarbonImmutable::parse('2026-10-03'));
        $store = new PlayerAbilitySnapshotStore;
        $store->replace($snapshot, CarbonImmutable::parse('2026-10-03'), $service->fingerprint(), $store->manifest($snapshot));
        foreach (['reference', 'convergence'] as $case) {
            $corrupt = $snapshot;
            if ($case === 'reference') { $corrupt['all_age']['reference_component'] = 'missing'; }
            else { $corrupt['all_age']['components'][$snapshot['all_age']['reference_component']]['converged'] = false; }
            DB::table('player_ability_snapshots')->where('snapshot_key', 'current')->update(['payload' => json_encode($corrupt, JSON_THROW_ON_ERROR)]);
            $this->assertStringContainsString('unreadable', (new PlayerAbilitySnapshotStore)->current()['reason']);
        }
    }
}
