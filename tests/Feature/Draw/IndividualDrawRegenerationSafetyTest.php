<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\RoundRobinGenerationService;
use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, Fixture, FixtureResult, OrderOfPlay, Player, Registration, User, Venue};
use App\Services\DrawBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IndividualDrawRegenerationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_generators_preserve_scored_fixtures(): void
    {
        $draw = Draw::factory()->create(['locked' => false, 'published' => false]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR']);
        $result = FixtureResult::factory()->create(['fixture_id' => $fixture->id]);

        foreach ([fn () => app(RoundRobinGenerationService::class)->generate($draw),
            fn () => (new \ReflectionMethod(\App\Services\DrawService::class, 'generateRoundRobinFixtures'))
                ->invoke(app(\App\Services\DrawService::class), $draw),
            fn () => (new DrawBuilder($draw))->generateRoundRobinFixtures($draw)] as $generate) {
            try {
                $generate();
                $this->fail('Scored draw regeneration must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('draw', $exception->errors());
            }
            $this->assertDatabaseHas('fixtures', ['id' => $fixture->id]);
            $this->assertDatabaseHas('fixture_results', ['id' => $result->id]);
        }
    }

    public function test_legacy_regeneration_preserves_playoff_fixtures(): void
    {
        $draw = Draw::factory()->create(['locked' => false, 'published' => false]);
        $playoff = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN']);
        $rr = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR']);
        (new DrawBuilder($draw))->generateRoundRobinFixtures($draw);
        $this->assertDatabaseHas('fixtures', ['id' => $playoff->id]);
        $this->assertDatabaseMissing('fixtures', ['id' => $rr->id]);
    }

    public function test_generation_rejects_partial_play_even_without_set_rows(): void
    {
        $draw = Draw::factory()->create(['locked' => false, 'published' => false]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR', 'match_status' => 2]);
        try {
            app(RoundRobinGenerationService::class)->generate($draw);
            $this->fail('Partial play must prevent regeneration.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('draw', $exception->errors());
        }
        $this->assertDatabaseHas('fixtures', ['id' => $fixture->id, 'match_status' => 2]);
    }

    public function test_regeneration_removes_only_replaced_round_robin_bookings(): void
    {
        foreach ([false, true] as $legacy) {
            $draw = Draw::factory()->create(['locked' => false, 'published' => false]);
            $rr = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR']);
            $playoff = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN']);
            $venue = Venue::forceCreate(['name' => 'Synthetic regeneration venue']);
            foreach ([$rr, $playoff] as $fixture) {
                OrderOfPlay::create(['fixture_id' => $fixture->id, 'venue_id' => $venue->id,
                    'draw_id' => $draw->id, 'court' => '1', 'time' => '2026-10-25 08:00:00']);
            }
            if ($legacy) (new DrawBuilder($draw))->generateRoundRobinFixtures($draw);
            else app(RoundRobinGenerationService::class)->generate($draw);
            $this->assertDatabaseMissing('order_of_plays', ['fixture_id' => $rr->id]);
            $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $playoff->id]);
            $this->assertDatabaseHas('fixtures', ['id' => $playoff->id]);
        }
    }

    public function test_regeneration_preserves_published_schedule_when_draw_itself_is_unpublished(): void
    {
        $event = Event::factory()->create();
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => false, 'oop_published' => false]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR']);
        $snapshot = ['event_id' => $event->id, 'draw_id' => $draw->id, 'fixture_kind' => 'individual',
            'fixture_id' => $fixture->id, 'scheduled_at' => '2026-10-25 08:00:00', 'duration' => 60,
            'published_at' => now(), 'created_at' => now(), 'updated_at' => now()];
        DB::table('published_schedule_assignments')->insert($snapshot);
        foreach ([fn () => app(RoundRobinGenerationService::class)->generate($draw),
            fn () => (new DrawBuilder($draw))->generateRoundRobinFixtures($draw)] as $generate) {
            try {
                $generate();
                $this->fail('Published fixture identities must be preserved.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('Hide the published schedule', $exception->errors()['draw'][0]);
            }
            $this->assertDatabaseHas('fixtures', ['id' => $fixture->id]);
            $this->assertDatabaseHas('published_schedule_assignments', ['draw_id' => $draw->id, 'fixture_id' => $fixture->id]);
        }
    }

    public function test_failed_generation_rolls_back_fixture_deletion(): void
    {
        $draw = Draw::factory()->create(['locked' => false, 'published' => false]);
        $group = $draw->groups()->create(['name' => 'A']);
        $registrations = Registration::factory()->count(2)->create();
        $group->registrations()->sync($registrations->pluck('id')->all());
        $original = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR']);
        $venue = Venue::forceCreate(['name' => 'Synthetic rollback venue']);
        $booking = OrderOfPlay::create(['fixture_id' => $original->id, 'venue_id' => $venue->id,
            'draw_id' => $draw->id, 'court' => '1', 'time' => '2026-10-25 08:00:00']);
        $failCreation = true;
        Fixture::creating(function ($fixture) use ($draw, &$failCreation) {
            if ($failCreation && (int) $fixture->draw_id === (int) $draw->id) {
                throw new \RuntimeException('Simulated fixture creation failure');
            }
        });
        try {
            app(RoundRobinGenerationService::class)->generate($draw);
            $this->fail('Creation failure must propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated fixture creation failure', $exception->getMessage());
        } finally {
            $failCreation = false;
        }
        $this->assertSame([$original->id], $draw->drawFixtures()->pluck('id')->all());
        $this->assertDatabaseHas('order_of_plays', ['id' => $booking->id, 'fixture_id' => $original->id]);
    }

    public function test_legacy_attachment_routes_reject_duplicate_category_assignment_atomically(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id]);
        $other = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $registrations = collect(range(1, 2))->map(function () use ($category) {
            $registration = Registration::factory()->create();
            $registration->players()->attach(Player::factory()->create());
            CategoryEventRegistration::factory()->create([
                'category_event_id' => $category->id, 'registration_id' => $registration->id,
                'status' => 'active', 'payment_status_id' => 1,
            ]);
            return $registration;
        });
        $other->registrations()->attach($registrations[1]->id);
        foreach ([
            ['add.draw.registration', ['players' => $registrations->pluck('id')->all()]],
            ['draws.addPlayers', ['players' => $registrations->pluck('id')->all()]],
            ['draws.players.update', ['players' => $registrations->pluck('id')->all()]],
            ['draws.import-category', []],
            ['add.draw.registration.category', ['category' => $category->id]],
        ] as [$route, $payload]) {
            $this->postJson(route($route, $draw), $payload)->assertUnprocessable();
            $this->assertSame(0, $draw->registrations()->count());
            $this->assertSame(1, $other->registrations()->count());
        }
    }
}
