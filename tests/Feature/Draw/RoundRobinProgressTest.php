<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, Fixture, FixtureResult, Player, Registration, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
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
}
