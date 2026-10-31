<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, Fixture, FixtureResult, Player, Registration, User};
use App\Domain\Draws\Services\ByeAdvancementService;
use App\Services\DrawService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RoundRobinProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_explains_progression_and_only_offers_confirmation_after_all_results(): void
    {
        [$draw, $category] = $this->draw([['name' => 'Main', 'slug' => 'main', 'enabled' => true, 'size' => 2, 'positions' => [1, 2]]]);
        $this->completeGroup($draw, 'A', $this->entrants($category, 2));
        $count = Fixture::count();
        $url = route('backend.draw.roundrobin.show', $draw);
        $page = $this->get($url)->assertOk()->assertSee('All 1 group results are complete')
            ->assertSee('Review &amp; progress to playoffs', false)->assertSee('Qualification and progression rules')
            ->assertSee('positions 1, 2 from each group');
        if (getenv('DRAW_GUIDE_SNAPSHOT_DIR')) file_put_contents(getenv('DRAW_GUIDE_SNAPSHOT_DIR').'/progression-ready.html', $page->getContent());
        $this->assertSame($count, Fixture::count());
        $registrationIds = $draw->drawFixtures()->where('stage', 'RR')->firstOrFail();
        Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'registration1_id' => $registrationIds->registration1_id, 'registration2_id' => $registrationIds->registration2_id]);
        $created = $this->get($url)->assertOk()->assertSee('Playoffs created')->assertSee('Review &amp; refresh playoffs', false);
        if (getenv('DRAW_GUIDE_SNAPSHOT_DIR')) file_put_contents(getenv('DRAW_GUIDE_SNAPSHOT_DIR').'/progression-created.html', $created->getContent());
        $draw->update(['locked' => true]);
        $this->get($url)->assertOk()->assertSee('Unlock it before progressing')->assertDontSee('class="btn btn-primary progress-draw"', false);
        $draw->update(['locked' => false]);
        FixtureResult::query()->delete();
        $this->get($url)->assertOk()->assertSee('some group results are incomplete')->assertDontSee('progress-draw"', false);
    }

    private function draw(array $config, bool $published = true): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $event = Event::factory()->create(['eventType' => 6]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create([
            'event_id' => $event->id,
            'category_event_id' => $category->id,
            'published' => $published,
            'locked' => false,
        ]);
        $draw->settings()->create([
            'workflow' => 'round_robin_playoffs',
            'boxes' => 1,
            'num_sets' => 1,
            'playoff_config' => $config,
        ]);

        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);

        return [$draw, $category];
    }

    private function entrants(CategoryEvent $category, int $count): array
    {
        return collect(range(1, $count))->map(function () use ($category) {
            $registration = Registration::factory()->create();
            $registration->players()->attach(Player::factory()->create()->id);
            CategoryEventRegistration::factory()->create([
                'category_event_id' => $category->id,
                'registration_id' => $registration->id,
                'payment_status_id' => 1,
            ]);

            return $registration;
        })->all();
    }

    private function completeGroup(Draw $draw, string $name, array $registrations): void
    {
        $group = $draw->groups()->create(['name' => $name]);
        foreach ($registrations as $seed => $registration) {
            $group->registrations()->attach($registration->id, ['seed' => $seed + 1]);
        }

        $match = 1 + (int) $draw->drawFixtures()->where('stage', 'RR')->max('match_nr');
        for ($one = 0; $one < count($registrations); $one++) {
            for ($two = $one + 1; $two < count($registrations); $two++) {
                $winner = $registrations[$one];
                $loser = $registrations[$two];
                $fixture = Fixture::factory()->create([
                    'draw_id' => $draw->id,
                    'draw_group_id' => $group->id,
                    'stage' => 'RR',
                    'match_nr' => $match++,
                    'registration1_id' => $winner->id,
                    'registration2_id' => $loser->id,
                    'winner_registration' => $winner->id,
                    'match_status' => 3,
                ]);
                FixtureResult::factory()->create([
                    'fixture_id' => $fixture->id,
                    'winner_registration' => $winner->id,
                    'loser_registration' => $loser->id,
                ]);
            }
        }
    }

    private function progress(Draw $draw)
    {
        $review = $this->getJson(route('backend.draw.progress-review', $draw))
            ->assertOk()
            ->json('review');

        return $this->postJson(route('backend.draw.progress', $draw), [
            'confirmed_group_ids' => collect($review['groups'])->pluck('id')->all(),
            'standings_snapshot' => $review['snapshot'],
        ]);
    }

    public function test_progress_requires_every_round_robin_result(): void
    {
        [$draw] = $this->draw([[
            'name' => 'Main', 'slug' => 'main', 'size' => 2,
            'positions' => [1, 2], 'enabled' => true,
        ]]);
        $group = $draw->groups()->create(['name' => 'A']);
        Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id, 'stage' => 'RR']);

        $this->postJson(route('backend.draw.progress', $draw))
            ->assertUnprocessable()
            ->assertJsonPath('round_robin.remaining', 1);

        $this->assertSame(0, $draw->drawFixtures()->where('stage', '!=', 'RR')->count());
    }

    public function test_event_overview_enables_progress_only_after_the_last_round_robin_result(): void
    {
        [$draw] = $this->draw([[
            'name' => 'Main', 'slug' => 'main', 'size' => 2,
            'positions' => [1, 2], 'enabled' => true,
        ]]);
        $group = $draw->groups()->create(['name' => 'A']);
        $fixture = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'draw_group_id' => $group->id,
            'stage' => 'RR',
        ]);

        $this->get(route('headOffice.show', $draw->event_id))
            ->assertOk()
            ->assertSee('1 result left')
            ->assertSee('data-progress-ready="0"', false);

        FixtureResult::factory()->create(['fixture_id' => $fixture->id]);

        $this->get(route('headOffice.show', $draw->event_id))
            ->assertOk()
            ->assertSee('Review &amp; progress', false)
            ->assertSee('data-progress-ready="1"', false);
    }

    public function test_published_position_pair_draw_progresses_without_duplicate_fixtures(): void
    {
        [$draw, $category] = $this->draw([[
            'name' => '1st/2nd', 'slug' => 'main', 'size' => 2,
            'positions' => [1, 2], 'enabled' => true,
        ]]);
        $registrations = $this->entrants($category, 4);
        $this->completeGroup($draw, 'A', $registrations);

        $this->progress($draw)
            ->assertOk()->assertJsonPath('progress.created', 1);
        $this->progress($draw)
            ->assertOk()->assertJsonPath('progress.created', 0);

        $playoff = $draw->drawFixtures()->where('stage', 'MAIN')->sole();
        $this->assertSame($registrations[0]->id, $playoff->registration1_id);
        $this->assertSame($registrations[1]->id, $playoff->registration2_id);
        $this->assertSame(1, $draw->drawFixtures()->where('stage', '!=', 'RR')->count());
    }

    public function test_progress_generates_larger_playoffs_once_and_reconciles_winners(): void
    {
        [$draw, $category] = $this->draw([[
            'name' => 'Main Draw', 'slug' => 'main', 'size' => 4,
            'positions' => [1, 2], 'enabled' => true,
        ]]);
        $registrations = $this->entrants($category, 4);
        $this->completeGroup($draw, 'A', array_slice($registrations, 0, 2));
        $this->completeGroup($draw, 'B', array_slice($registrations, 2, 2));

        $first = $this->progress($draw)
            ->assertOk()->assertJsonPath('progress.created', 4);
        $firstRound = $draw->drawFixtures()->where('stage', 'MAIN')->where('round', 1)->orderBy('match_nr')->get();
        $final = $draw->drawFixtures()->where('stage', 'MAIN')->where('round', 2)->where('position', 1)->sole();

        foreach ($firstRound as $fixture) {
            $fixture->update(['winner_registration' => $fixture->registration1_id, 'match_status' => 3]);
            FixtureResult::factory()->create([
                'fixture_id' => $fixture->id,
                'winner_registration' => $fixture->registration1_id,
                'loser_registration' => $fixture->registration2_id,
            ]);
        }

        $this->progress($draw)
            ->assertOk()->assertJsonPath('progress.advancedSlots', 4);

        $final->refresh();
        $this->assertNotNull($final->registration1_id);
        $this->assertNotNull($final->registration2_id);
        $this->assertSame(4, $draw->drawFixtures()->where('stage', '!=', 'RR')->count());
        $first->assertJsonPath('progress.playoffCount', 4);
    }

    public function test_locked_draw_cannot_be_progressed(): void
    {
        [$draw] = $this->draw([[
            'name' => 'Main', 'slug' => 'main', 'size' => 2,
            'positions' => [1, 2], 'enabled' => true,
        ]]);
        $draw->update(['locked' => true]);

        $this->postJson(route('backend.draw.progress', $draw))->assertForbidden();
    }

    private function scheduledPlayoffPlaceholders(): array
    {
        [$draw, $category] = $this->draw([
            ['name' => 'Main', 'slug' => 'main', 'size' => 4, 'positions' => [1, 2], 'enabled' => true],
            ['name' => 'Plate', 'slug' => 'plate', 'size' => 4, 'positions' => [3, 4], 'enabled' => true],
        ], false);
        $entrants = $this->entrants($category, 8);
        $this->completeGroup($draw, 'A', array_slice($entrants, 0, 4));
        $this->completeGroup($draw, 'B', array_slice($entrants, 4, 4));
        $controller = app(\App\Http\Controllers\Backend\RoundRobinController::class);
        $this->assertSame(8, $controller->prepareSchedulePlayoffs($draw));
        $venue = \App\Models\Venue::forceCreate(['name' => 'Progression test venue']);
        foreach ($draw->drawFixtures()->where('stage', '!=', 'RR')->get() as $fixture) {
            \App\Models\OrderOfPlay::create([
                'draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id,
                'court' => '1', 'time' => '2026-10-31 10:00:00', 'duration_minutes' => 15, 'gap_minutes' => 0,
            ]);
        }
        $draw->update(['published' => true, 'oop_published' => true]);
        return [$draw, $entrants];
    }

    public function test_progress_resolves_scheduled_main_and_plate_sources_in_place_once(): void
    {
        [$draw, $entrants] = $this->scheduledPlayoffPlaceholders();
        $before = $draw->drawFixtures()->where('stage', '!=', 'RR')->orderBy('id')->get();
        $bookings = DB::table('order_of_plays')->orderBy('id')->get()->toJson();
        // A matching slot may already have resolved; it must remain untouched.
        $before->first()->update(['registration1_id' => $entrants[0]->id]);

        $this->progress($draw)->assertOk()->assertJsonPath('progress.created', 0)
            ->assertJsonPath('progress.resolved', 4)->assertJsonPath('progress.unscheduled', 0);
        $opening = $draw->drawFixtures()->where('stage', '!=', 'RR')->where('round', 1)->orderBy('match_nr')->get();
        $expected = [[0, 5], [4, 1], [2, 7], [6, 3]];
        foreach ($opening as $index => $fixture) {
            $this->assertSame($entrants[$expected[$index][0]]->id, $fixture->registration1_id);
            $this->assertSame($entrants[$expected[$index][1]]->id, $fixture->registration2_id);
        }
        $after = $draw->drawFixtures()->where('stage', '!=', 'RR')->orderBy('id')->get();
        $this->assertSame($before->modelKeys(), $after->modelKeys());
        foreach ($after as $index => $fixture) {
            foreach (['parent_fixture_id', 'loser_parent_fixture_id', 'match_nr', 'stage', 'round'] as $column) {
                $this->assertSame($before[$index]->$column, $fixture->$column);
            }
        }
        $this->assertSame($bookings, DB::table('order_of_plays')->orderBy('id')->get()->toJson());
        $this->progress($draw)->assertOk()->assertJsonPath('progress.resolved', 0)->assertJsonPath('progress.created', 0);
        $this->assertSame(20, $draw->drawFixtures()->count());
        $this->assertDatabaseCount('fixture_results', 12);
    }

    public function test_progress_rejects_conflicting_occupied_source_and_rolls_back_all_slots(): void
    {
        [$draw, $entrants] = $this->scheduledPlayoffPlaceholders();
        $last = $draw->drawFixtures()->where('stage', 'PLATE')->where('round', 1)->orderByDesc('match_nr')->firstOrFail();
        $last->update(['registration2_id' => $entrants[0]->id]);
        $before = $draw->drawFixtures()->orderBy('id')->get()->toJson();
        $bookings = DB::table('order_of_plays')->orderBy('id')->get()->toJson();
        $this->progress($draw)->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertSame($before, $draw->drawFixtures()->orderBy('id')->get()->toJson());
        $this->assertSame($bookings, DB::table('order_of_plays')->orderBy('id')->get()->toJson());
    }

    public function test_progress_is_restricted_to_the_draws_event_administrators(): void
    {
        [$draw] = $this->scheduledPlayoffPlaceholders();
        $unrelatedAdmin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => Event::factory()->create()->id, 'user_id' => $unrelatedAdmin->id]);
        $this->actingAs($unrelatedAdmin);
        $this->getJson(route('backend.draw.progress-review', $draw))->assertForbidden();
        $this->postJson(route('backend.draw.progress', $draw))->assertForbidden();
        $this->assertSame(0, $draw->drawFixtures()->where('stage', '!=', 'RR')->whereNotNull('registration1_id')->count());
    }

    public function test_progress_does_not_fill_a_missing_slot_on_a_recorded_playoff(): void
    {
        [$draw, $entrants] = $this->scheduledPlayoffPlaceholders();
        $fixture = $draw->drawFixtures()->where('stage', 'MAIN')->where('round', 1)->firstOrFail();
        FixtureResult::factory()->create(['fixture_id' => $fixture->id, 'winner_registration' => $entrants[0]->id]);
        $before = $draw->drawFixtures()->orderBy('id')->get()->toJson();
        $this->progress($draw)->assertUnprocessable();
        $this->assertSame($before, $draw->drawFixtures()->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('fixture_results', 13);
    }

    public function test_strict_resolution_preserves_a_slot_without_a_group_source(): void
    {
        [$draw, $entrants] = $this->scheduledPlayoffPlaceholders();
        $fixture = $draw->drawFixtures()->where('stage', 'MAIN')->where('round', 1)->firstOrFail();
        $fixture->update(['registration2_source_group_id' => null, 'registration2_source_position' => null,
            'registration2_id' => $entrants[5]->id]);
        $this->progress($draw)->assertOk();
        $this->assertSame($entrants[0]->id, $fixture->fresh()->registration1_id);
        $this->assertSame($entrants[5]->id, $fixture->fresh()->registration2_id);
    }

    public function test_progress_review_returns_each_group_in_natural_order_with_full_standings(): void
    {
        [$draw, $category] = $this->draw([[
            'name' => 'Main Draw', 'slug' => 'main', 'size' => 4,
            'positions' => [1, 2], 'enabled' => true,
        ]]);
        $registrations = $this->entrants($category, 4);
        $this->completeGroup($draw, 'Group 10', array_slice($registrations, 0, 2));
        $this->completeGroup($draw, 'Group 2', array_slice($registrations, 2, 2));

        $response = $this->getJson(route('backend.draw.progress-review', $draw))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('review.groups.0.name', 'Group 2')
            ->assertJsonPath('review.groups.1.name', 'Group 10')
            ->assertJsonCount(2, 'review.groups.0.standings')
            ->assertJsonStructure(['review' => ['snapshot', 'groups' => [[
                'id', 'name', 'standings' => [[
                    'position', 'registration_id', 'player', 'played', 'wins', 'draws', 'points', 'losses',
                    'sets_won', 'sets_lost', 'games_won', 'games_lost', 'tiebreak',
                ]],
            ]]]]);

        $this->assertSame(64, strlen($response->json('review.snapshot')));
    }

    public function test_timed_draw_review_includes_draws_in_played_and_canonical_points(): void
    {
        [$draw, $category] = $this->draw([[
            'name' => 'Main', 'slug' => 'main', 'size' => 2,
            'positions' => [1, 2], 'enabled' => true,
        ]]);
        $draw->settings()->update(['score_format' => 'timed']);
        $registrations = $this->entrants($category, 4);
        $this->completeGroup($draw, 'A', $registrations);
        foreach ($draw->drawFixtures()->where('stage', 'RR')->get() as $fixture) {
            $tied = $fixture->registration2_id === $registrations[1]->id;
            $fixture->update(['winner_registration' => $tied ? null : $fixture->registration1_id]);
            $fixture->fixtureResults()->update([
                'registration1_score' => 3, 'registration2_score' => $tied ? 3 : 1,
                'winner_registration' => $tied ? null : $fixture->registration1_id,
                'loser_registration' => $tied ? null : $fixture->registration2_id,
            ]);
        }
        $review = $this->getJson(route('backend.draw.progress-review', $draw))->assertOk()->json('review');
        $rows = collect($review['groups'][0]['standings'])->keyBy('registration_id');
        foreach ($rows as $row) {
            $this->assertSame(3, $row['played']);
            $this->assertSame($row['wins'] + $row['draws'] + $row['losses'], $row['played']);
            $this->assertSame(2 * $row['wins'] + $row['draws'], $row['points']);
        }
        $this->assertSame(1, $rows[$registrations[0]->id]['draws']);
        $this->assertSame(2, $rows[$registrations[0]->id]['wins']);
        $this->assertSame(5, $rows[$registrations[0]->id]['points']);
        $this->assertSame(1, $rows[$registrations[1]->id]['draws']);
        $this->assertSame(2, $rows[$registrations[1]->id]['wins']);
        $this->assertSame(5, $rows[$registrations[1]->id]['points']);
        $this->progress($draw)->assertOk();
    }

    public function test_progress_requires_every_group_confirmation_and_current_standings_snapshot(): void
    {
        [$draw, $category] = $this->draw([[
            'name' => 'Main', 'slug' => 'main', 'size' => 2,
            'positions' => [1, 2], 'enabled' => true,
        ]]);
        $this->completeGroup($draw, 'A', $this->entrants($category, 2));
        $review = $this->getJson(route('backend.draw.progress-review', $draw))->assertOk()->json('review');

        $this->postJson(route('backend.draw.progress', $draw), [
            'confirmed_group_ids' => [],
            'standings_snapshot' => $review['snapshot'],
        ])->assertUnprocessable()->assertJsonPath(
            'message',
            'Review and confirm every round-robin group before progressing to the playoffs.'
        );

        $this->postJson(route('backend.draw.progress', $draw), [
            'confirmed_group_ids' => collect($review['groups'])->pluck('id')->all(),
            'standings_snapshot' => str_repeat('0', 64),
        ])->assertStatus(409)->assertJsonPath(
            'message',
            'The round-robin standings changed during review. Please review every group again.'
        );

        $this->assertSame(0, $draw->drawFixtures()->where('stage', '!=', 'RR')->count());
    }

    public function test_progress_rechecks_the_confirmed_snapshot_after_acquiring_the_draw_lock(): void
    {
        [$draw, $category] = $this->draw([[
            'name' => 'Main', 'slug' => 'main', 'size' => 2,
            'positions' => [1, 2], 'enabled' => true,
        ]]);
        $this->completeGroup($draw, 'A', $this->entrants($category, 2));
        $review = $this->getJson(route('backend.draw.progress-review', $draw))->assertOk()->json('review');
        $fixture = $draw->drawFixtures()->where('stage', 'RR')->sole();
        $realBuilder = app(DrawService::class);
        $calls = 0;
        $this->partialMock(DrawService::class, function ($mock) use ($realBuilder, $fixture, &$calls) {
            $mock->shouldReceive('loadRoundRobinHub')->andReturnUsing(function ($currentDraw) use ($realBuilder, $fixture, &$calls) {
                $hub = $realBuilder->loadRoundRobinHub($currentDraw);
                if (++$calls === 1) {
                    // A different scorer commits a correction after the first
                    // snapshot check, before progression acquires its lock.
                    $fixture->fixtureResults()->update(['registration1_score' => 7, 'registration2_score' => 5]);
                }
                return $hub;
            });
        });
        $this->postJson(route('backend.draw.progress', $draw), [
            'confirmed_group_ids' => collect($review['groups'])->pluck('id')->all(),
            'standings_snapshot' => $review['snapshot'],
        ])->assertStatus(409)->assertJsonPath('message',
            'The round-robin standings changed during review. Please review every group again.');
        $this->assertSame(2, $calls);
        $this->assertSame(0, $draw->drawFixtures()->where('stage', '!=', 'RR')->count());
        $this->assertDatabaseCount('fixture_results', 1);
        $this->assertDatabaseHas('fixture_results', ['fixture_id' => $fixture->id, 'registration1_score' => 7, 'registration2_score' => 5]);
    }

    public function test_correcting_a_bracket_winner_replaces_winner_and_loser_in_downstream_fixtures(): void
    {
        [$draw, $category] = $this->draw([]);
        [$one, $two, $three, $four] = $this->entrants($category, 4);

        $final = Fixture::factory()->create([
            'draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 2, 'match_nr' => 103,
        ]);
        $third = Fixture::factory()->create([
            'draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 2, 'match_nr' => 104,
        ]);
        $semiOne = Fixture::factory()->create([
            'draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 1, 'match_nr' => 101,
            'registration1_id' => $one->id, 'registration2_id' => $two->id,
            'parent_fixture_id' => $final->id, 'loser_parent_fixture_id' => $third->id,
        ]);
        $semiTwo = Fixture::factory()->create([
            'draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 1, 'match_nr' => 102,
            'registration1_id' => $three->id, 'registration2_id' => $four->id,
            'parent_fixture_id' => $final->id, 'loser_parent_fixture_id' => $third->id,
        ]);

        $service = app(DrawService::class);
        $service->saveBracketScore($semiOne, [[3, 1]]);
        $service->saveBracketScore($semiTwo, [[0, 3]]);

        $this->assertSame($four->id, $final->fresh()->registration2_id);
        $this->assertSame($three->id, $third->fresh()->registration2_id);

        $service->saveBracketScore($semiTwo->fresh(), [[3, 0]]);

        $this->assertSame($three->id, $final->fresh()->registration2_id);
        $this->assertSame($four->id, $third->fresh()->registration2_id);
        $this->assertSame($three->id, $semiTwo->fresh()->winner_registration);
        $this->assertDatabaseCount('fixture_results', 2);
        $this->assertDatabaseHas('fixture_results', [
            'fixture_id' => $semiTwo->id,
            'registration1_score' => 3,
            'registration2_score' => 0,
            'winner_registration' => $three->id,
        ]);
    }

    public function test_winner_changing_correction_is_blocked_after_downstream_result_exists(): void
    {
        [$draw, $category] = $this->draw([]);
        [$one, $two, $otherFinalist] = $this->entrants($category, 3);

        $final = Fixture::factory()->create([
            'draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 2, 'match_nr' => 202,
            'registration2_id' => $otherFinalist->id,
        ]);
        $semi = Fixture::factory()->create([
            'draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 1, 'match_nr' => 201,
            'registration1_id' => $one->id, 'registration2_id' => $two->id,
            'parent_fixture_id' => $final->id,
        ]);

        $service = app(DrawService::class);
        $service->saveBracketScore($semi, [[3, 1]]);
        $service->saveBracketScore($final->fresh(), [[3, 0]]);

        try {
            $service->saveBracketScore($semi->fresh(), [[1, 3]]);
            $this->fail('Expected the correction to be blocked.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertStringContainsString('Delete that downstream result first', $exception->getMessage());
        }

        $this->assertSame($one->id, $semi->fresh()->winner_registration);
        $this->assertSame($one->id, $final->fresh()->registration1_id);
        $this->assertDatabaseHas('fixture_results', [
            'fixture_id' => $semi->id,
            'registration1_score' => 3,
            'registration2_score' => 1,
        ]);
    }

    public function test_bracket_score_cascades_a_loser_into_a_terminal_bye_playoff(): void
    {
        [$draw, $category] = $this->draw([]);
        [$byeWinner, $winner, $loser] = $this->entrants($category, 3);

        $placingMatch = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'stage' => 'PLATE',
            'round' => 2,
            'match_nr' => 1007,
            'position' => 7,
            'playoff_type' => '7th/8th',
        ]);
        Fixture::factory()->create([
            'draw_id' => $draw->id,
            'stage' => 'PLATE',
            'round' => 1,
            'match_nr' => 1004,
            'registration1_id' => $byeWinner->id,
            'registration2_id' => null,
            'winner_registration' => $byeWinner->id,
            'loser_parent_fixture_id' => $placingMatch->id,
        ]);
        $playedSemi = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'stage' => 'PLATE',
            'round' => 1,
            'match_nr' => 1005,
            'registration1_id' => $winner->id,
            'registration2_id' => $loser->id,
            'loser_parent_fixture_id' => $placingMatch->id,
        ]);

        // The earlier BYE cannot resolve the placing match until its other
        // semifinal has supplied a loser.
        app(ByeAdvancementService::class)->advance($draw);
        $this->assertNull($placingMatch->fresh()->winner_registration);

        app(DrawService::class)->saveBracketScore($playedSemi, [[3, 2]]);

        $placingMatch->refresh();
        $this->assertSame($loser->id, $placingMatch->registration2_id);
        $this->assertSame($loser->id, $placingMatch->winner_registration);
        $this->assertSame(3, (int) $placingMatch->match_status);
        $this->assertSame(0, (int) $placingMatch->scheduled);
        $this->assertDatabaseMissing('fixture_results', [
            'fixture_id' => $placingMatch->id,
        ]);
    }
}
