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

    public function test_timed_partial_totals_stay_excluded_until_canonical_final_score_save(): void
    {
        $event = Event::factory()->create(['start_date' => '2026-10-01', 'end_date' => '2026-11-02', 'published' => true]);
        $venue = Venue::forceCreate(['name' => 'Synthetic timed courts']);
        $players = Player::factory()->count(2)->create();
        $fixture = $this->match($event, $venue, 'Orange Ball', $players[0], $players[1]);
        $draw = $fixture->draw;
        $draw->settings->update(['score_format' => 'timed', 'workflow' => 'round_robin_playoffs', 'num_sets' => 1]);
        $fixture->update(['stage' => 'RR']);
        $ability = app(PlayerSharedAbilityService::class);
        $date = CarbonImmutable::parse('2026-10-31');
        foreach ([[0, 1], [2, 1], [0, 4], [1, 2], [1, 2], [4, 0], [2, 1], [1, 2], [0, 2]] as [$first, $second]) {
            $fixture->fixtureResults()->update(['registration1_score' => $first, 'registration2_score' => $second]);
            $fixture->update(['match_status' => 2, 'winner_registration' => null]);
            foreach (['round_robin_playoffs', 'round_robin'] as $workflow) {
                $draw->settings->update(['workflow' => $workflow]);
                $this->assertSame([], $ability->calculateSnapshot($date)['all_age']['ratings'], 'A valid timed score is not proof the live match finished.');
            }
            app(\App\Services\DrawService::class)->saveScore($fixture->fresh(), [[$first, $second]]);
            $fixture->refresh();
            $this->assertSame(1, (int) $fixture->match_status);
            $this->assertSame((int) ($first > $second ? $fixture->registration1_id : $fixture->registration2_id), (int) $fixture->winner_registration);
            $sources = [];
            $snapshot = $ability->historySnapshot($date, $sources);
            $this->assertCount(1, $snapshot['all_age']['history_evidence']['matches']);
            $this->assertDatabaseCount('fixture_results', 1);
        }
        app(\App\Services\DrawService::class)->saveScore($fixture->fresh(), [[2, 2]]);
        $this->assertSame(1, (int) $fixture->fresh()->match_status);
        $this->assertSame([], $ability->calculateSnapshot($date)['all_age']['ratings'], 'A completed timed draw has no winner-based rating evidence.');
    }

    public function test_every_disconnected_played_component_has_a_low_confidence_provisional_rating_and_saved_evidence(): void
    {
        $event = Event::factory()->create(['start_date' => '2026-10-01', 'end_date' => '2026-11-02', 'published' => true]);
        $venue = Venue::forceCreate(['name' => 'Synthetic disconnected courts']);
        $players = Player::factory()->count(4)->create();
        $this->match($event, $venue, 'Green ball', $players[0], $players[1]);
        $this->match($event, $venue, 'Orange ball', $players[2], $players[3]);
        $service = app(PlayerSharedAbilityService::class);
        $sources = [];
        $date = CarbonImmutable::parse('2026-10-31');
        $snapshot = $service->historySnapshot($date, $sources);
        $this->assertCount(4, $snapshot['all_age']['ratings']);
        foreach ($snapshot['all_age']['ratings'] as $rating) $this->assertIsFloat($rating['score']);
        $local = $snapshot['all_age']['ratings'][$players[2]->id];
        $this->assertFalse($local['comparable']);
        $this->assertSame('provisional_local', $local['rating_status']);
        $this->assertSame('Low', $local['confidence_label']);
        $this->assertLessThanOrEqual(39, $local['confidence_index']);
        $store = new PlayerAbilitySnapshotStore;
        $store->replace($snapshot, $date, $service->fingerprint(), $store->manifest($snapshot));
        $this->assertSame($local['score'], (new PlayerAbilitySnapshotStore)->current()['all_age']['ratings'][$players[2]->id]['score']);
        $projection = app(\App\Services\Performance\PlayerRatingGroupProjection::class)->project($snapshot, $local['component']);
        $this->assertSame($local['score'], $projection[$players[2]->id]['within_group_score']);
        $builder = new \App\Services\Performance\PlayerRatingEvidenceBuilder;
        $builder->append($snapshot, $sources, 11);
        $this->assertCount(2, collect($builder->all())->where('cohort', AllAgeRatingModel::COHORT));
    }

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

    public function test_three_green_ball_results_join_the_same_cross_age_head_to_head_reference_and_saved_history(): void
    {
        $event = Event::factory()->create(['start_date' => '2026-10-01', 'end_date' => '2026-11-02', 'published' => true, 'results_published' => false]);
        $venue = Venue::forceCreate(['name' => 'Synthetic mixed levels courts']);
        DB::table('event_venue_courts')->insert(['event_id' => $event->id, 'venue_id' => $venue->id, 'label' => '1', 'ball_type' => 'green', 'active' => true]);
        $players = Player::factory()->count(5)->create();
        $fixtures = [
            $this->match($event, $venue, 'Green ball', $players[0], $players[1]),
            $this->match($event, $venue, 'Green ball', $players[0], $players[2]),
            $this->match($event, $venue, 'Green ball', $players[3], $players[0]),
            $this->match($event, $venue, 'U18 Girls A', $players[3], $players[4]),
        ];
        // A different playing condition/category shares an opponent with the green-ball matches.
        DB::table('event_venue_courts')->insert(['event_id' => $event->id, 'venue_id' => $venue->id, 'label' => '2', 'ball_type' => 'yellow', 'active' => true]);
        $fixtures[3]->oop()->update(['court' => '2']);
        $service = app(PlayerSharedAbilityService::class);
        $sources = [];
        $snapshot = $service->historySnapshot(CarbonImmutable::parse('2026-10-31'), $sources);
        $this->assertCount(1, $snapshot['all_age']['components']);
        $this->assertCount(5, $snapshot['all_age']['ratings']);
        $this->assertNotNull($snapshot['all_age']['ratings'][$players[0]->id]['score']);
        $this->assertSame($snapshot['all_age']['ratings'][$players[0]->id]['component'], $snapshot['all_age']['ratings'][$players[4]->id]['component']);
        $builder = new \App\Services\Performance\PlayerRatingEvidenceBuilder;
        $builder->append($snapshot, $sources, 11);
        $personal = collect($builder->all())->where('cohort', AllAgeRatingModel::COHORT)->filter(fn ($row) => in_array($players[0]->id, $row['players'], true));
        $this->assertCount(3, $personal);
        $this->assertSame(2, $personal->filter(fn ($row) => $row['players'][$row['winner_side'] - 1] === $players[0]->id)->count());
        $this->assertSame([], $snapshot['all_age']['excluded']);
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
        $this->assertSame(array_column($snapshot['all_age']['ratings'], 'score'), array_column($rebuilt['all_age']['ratings'], 'score'), 'Changing ball metadata must not change the shared head-to-head model.');
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
