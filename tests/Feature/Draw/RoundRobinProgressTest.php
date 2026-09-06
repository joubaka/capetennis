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

    public function test_published_position_pair_draw_progresses_without_duplicate_fixtures(): void
    {
        [$draw, $category] = $this->draw([[
            'name' => '1st/2nd', 'slug' => 'main', 'size' => 2,
            'positions' => [1, 2], 'enabled' => true,
        ]]);
        $registrations = $this->entrants($category, 4);
        $this->completeGroup($draw, 'A', $registrations);

        $this->postJson(route('backend.draw.progress', $draw))
            ->assertOk()->assertJsonPath('progress.created', 1);
        $this->postJson(route('backend.draw.progress', $draw))
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

        $first = $this->postJson(route('backend.draw.progress', $draw))
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

        $this->postJson(route('backend.draw.progress', $draw))
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
