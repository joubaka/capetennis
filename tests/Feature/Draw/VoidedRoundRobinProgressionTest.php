<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\RoundRobinGenerationService;
use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, Player, Registration, User, Venue};
use App\Services\Draw\{HybridRoundRobinService, TwoStageRoundRobinService};
use App\Services\DrawService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VoidedRoundRobinProgressionTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(bool $hybrid = false): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create(['eventType' => 6, 'published' => true]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id,
            'published' => false, 'oop_published' => false, 'locked' => false]);
        $this->actingAs($actor)->post(route('draw.setup.store', $draw), ['workflow' => 'round_robin_round_robin',
            'groups' => 2, 'second_stage_format' => $hybrid ? HybridRoundRobinService::FORMAT : 'equal_positions'])->assertRedirect();
        $draw = $draw->fresh();
        $draw->settings->update(['score_format' => 'one_full_set', 'num_sets' => 1]);
        $withdrawn = null;
        foreach ($draw->groups()->orderBy('name')->get() as $group) {
            foreach (range(1, 3) as $seed) {
                $registration = Registration::factory()->create();
                $registration->players()->attach(Player::factory()->create()->id);
                CategoryEventRegistration::factory()->create(['category_event_id' => $category->id,
                    'registration_id' => $registration->id, 'payment_status_id' => 1]);
                $draw->registrations()->attach($registration->id);
                $group->registrations()->attach($registration->id, ['seed' => $seed]);
                $withdrawn ??= $registration->id;
            }
        }
        app(RoundRobinGenerationService::class)->generate($draw->fresh());
        foreach ($draw->drawFixtures()->whereNotNull('registration1_id')->get() as $fixture) {
            app(DrawService::class)->saveScore($fixture,
                $fixture->registration1_id < $fixture->registration2_id ? [[6, 0]] : [[0, 6]]);
        }
        return [$draw->fresh(), $withdrawn];
    }

    private function markVoided(Draw $draw, int $registrationId): void
    {
        $draw->drawFixtures()->where('stage', 'RR')->where(fn ($query) => $query
            ->where('registration1_id', $registrationId)->orWhere('registration2_id', $registrationId))
            ->update(['round_robin_voided_registration_id' => $registrationId]);
    }

    public function test_equal_position_progression_prunes_only_unavailable_sources_and_excludes_void_player(): void
    {
        [$draw, $withdrawn] = $this->scenario();
        $before = $draw->drawFixtures()->pluck('id');
        $results = DB::table('fixture_results')->orderBy('id')->get()->toJson();
        $removed = $draw->drawFixtures()->where('registration1_source_position', 3)->pluck('id');
        DB::transaction(function () use ($draw, $withdrawn) {
            $this->markVoided($draw, $withdrawn);
            app(TwoStageRoundRobinService::class)->reconcileVoidedSources($draw, $withdrawn);
        });
        $this->assertSame($before->diff($removed)->values()->all(), $draw->drawFixtures()->pluck('id')->all());
        $this->assertSame($results, DB::table('fixture_results')->orderBy('id')->get()->toJson());
        $service = app(TwoStageRoundRobinService::class);
        $this->assertTrue($service->preparedStructureIsValid($draw->fresh()));
        $review = $service->review($draw->fresh());
        $this->assertNotContains($withdrawn, collect($review['groups'])->flatMap(fn ($group) => $group['standings'])->pluck('registration_id')->all());
        $service->progress($draw->fresh(), ['confirmed_group_ids' => collect($review['groups'])->pluck('id')->all(), 'standings_snapshot' => $review['snapshot']]);
        $this->assertSame(5, $draw->groups()->where('round_robin_phase', 2)->withCount('groupRegistrations')->get()->sum('group_registrations_count'));
    }

    public function test_published_affected_placeholder_blocks_and_rolls_back_void(): void
    {
        [$draw, $withdrawn] = $this->scenario();
        $fixture = $draw->drawFixtures()->where('registration1_source_position', 3)->firstOrFail();
        $venue = Venue::forceCreate(['name' => 'Example courts']);
        DB::table('published_schedule_assignments')->insert(['event_id' => $draw->event_id, 'draw_id' => $draw->id,
            'fixture_kind' => 'individual', 'fixture_id' => $fixture->id, 'venue_id' => $venue->id, 'court' => '1',
            'scheduled_at' => '2026-11-27 10:00:00', 'duration' => 30, 'published_at' => now()]);
        try {
            DB::transaction(function () use ($draw, $withdrawn) {
                $this->markVoided($draw, $withdrawn);
                app(TwoStageRoundRobinService::class)->reconcileVoidedSources($draw, $withdrawn);
            });
            $this->fail('Expected published schedule protection.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame(0, $draw->drawFixtures()->whereNotNull('round_robin_voided_registration_id')->count());
        $this->assertNotNull($fixture->fresh());
    }

    public function test_hybrid_progression_keeps_top_four_and_only_active_lower_field(): void
    {
        [$draw, $withdrawn] = $this->scenario(true);
        DB::transaction(function () use ($draw, $withdrawn) {
            $this->markVoided($draw, $withdrawn);
            app(TwoStageRoundRobinService::class)->reconcileVoidedSources($draw, $withdrawn);
        });
        $service = app(TwoStageRoundRobinService::class);
        $this->assertTrue($service->preparedStructureIsValid($draw->fresh()));
        $review = $service->review($draw->fresh());
        $service->progress($draw->fresh(), ['confirmed_group_ids' => collect($review['groups'])->pluck('id')->all(), 'standings_snapshot' => $review['snapshot']]);
        $draw = $draw->fresh();
        $state = $draw->settings->playoff_config['_second_round_robin'];
        $this->assertCount(0, $state['lower_fixture_ids']);
        $this->assertSame(1, $draw->groups()->whereKey($state['lower_group_id'])->first()->groupRegistrations()->count());
        $this->assertFalse($draw->drawFixtures()->where('stage', 'MAIN')->where(fn ($query) => $query
            ->where('registration1_id', $withdrawn)->orWhere('registration2_id', $withdrawn))->exists());
    }
}
