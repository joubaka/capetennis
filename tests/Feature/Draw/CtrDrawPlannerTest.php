<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\RoundRobinGenerationService;
use App\Models\{Category, CategoryEvent, CategoryEventRegistration, CtrDrawPlan, CtrEventCertification, Draw, Event, Fixture, Player, Registration, User};
use App\Services\Draw\CtrDrawPlanner;
use App\Services\Performance\PlayerAbilitySnapshotStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CtrDrawPlannerTest extends TestCase
{
    use RefreshDatabase;

    private array $snapshot = [];
    private Event $event;
    private User $actor;
    private CategoryEvent $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = Event::factory()->create(['name' => 'Fictional CTR morning', 'eventType' => 1]);
        $this->actor = User::factory()->create();
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $this->actor->id]);
        $this->actingAs($this->actor);
        CtrEventCertification::create(['event_id' => $this->event->id, 'certified' => true, 'updated_by' => $this->actor->id]);
        $this->category = CategoryEvent::factory()->create(['event_id' => $this->event->id,
            'category_id' => Category::factory()->create(['name' => 'Green Ball Singles'])->id]);
        $this->snapshot = ['reason' => null, 'snapshot_stale' => false, 'built_at' => 'fictional-v1', 'ball_ratings' => []];
        $store = Mockery::mock(PlayerAbilitySnapshotStore::class);
        $store->shouldReceive('current')->andReturnUsing(fn () => $this->snapshot);
        $store->shouldReceive('freshCurrent')->andReturnUsing(fn () => $this->snapshot);
        $this->app->instance(PlayerAbilitySnapshotStore::class, $store);
    }

    private function entrants(int $count, ?CategoryEvent $category = null, string $pool = 'standard', bool $rated = true): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $player = Player::factory()->create(['name' => 'Fictional Player '.($i + 1)]);
            $reg = Registration::factory()->create();
            $reg->players()->attach($player->id);
            $row = CategoryEventRegistration::factory()->create(['category_event_id' => ($category ?? $this->category)->id,
                'registration_id' => $reg->id, 'payment_status_id' => 1, 'status' => 'active', 'withdrawn_at' => null]);
            $ids[] = $row->id;
            if ($rated) $this->snapshot['ball_ratings'][$pool]['ratings'][$player->id] = ['score' => 90 - $i,
                'comparable' => true, 'component' => 'reference', 'confidence_label' => 'Medium'];
        }
        return $ids;
    }

    private function preview(int $target = 4): CtrDrawPlan
    {
        $this->post(route('backend.ctr-planner.preview', $this->event), ['target' => $target])->assertRedirect();
        return CtrDrawPlan::latest('id')->firstOrFail();
    }

    private function confirm(CtrDrawPlan $plan)
    {
        return $this->postJson(route('backend.ctr-planner.apply', $this->event), ['token' => $plan->token,
            'revision' => app(CtrDrawPlanner::class)->revision($plan)]);
    }

    public function test_group_sizes_and_configurable_preference(): void
    {
        foreach (['draw_groups', 'draw_group_registrations'] as $table) {
            $engine = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
                ->where('TABLE_NAME', $table)->value('ENGINE');
            $this->assertSame('innodb', strtolower($engine));
        }
        $planner = app(CtrDrawPlanner::class);
        foreach ([3 => [3], 4 => [4], 5 => [5], 6 => [3,3], 7 => [4,3], 8 => [4,4], 9 => [5,4], 10 => [5,5],
            11 => [4,4,3], 12 => [4,4,4], 13 => [5,4,4], 14 => [5,5,4], 15 => [5,5,5], 16 => [4,4,4,4]] as $n => $sizes) {
            $this->assertSame($sizes, $planner->sizes($n));
        }
        $this->assertSame([], $planner->sizes(2));
        $this->assertSame([3,3,3,3], $planner->sizes(12, 3));
        $this->assertSame([5,5], $planner->sizes(10, 5));
    }

    public function test_preview_is_read_only_and_confirmation_creates_exact_private_round_robins_once(): void
    {
        $this->entrants(11);
        $initial = $this->get(route('backend.ctr-planner.show', $this->event))->assertOk();
        $this->export('initial', $initial->getContent());
        $plan = $this->preview();
        $this->assertDatabaseCount('draws', 0);
        $this->assertDatabaseCount('fixtures', 0);
        $this->assertSame([4,4,3], array_map(fn ($c) => count($c['rows']), app(CtrDrawPlanner::class)->competitions($plan->proposal)));
        $review = $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk()->assertSee('= 6 prizes');
        $this->export('preview', $review->getContent());
        $this->confirm($plan)->assertRedirect();
        $this->assertDatabaseCount('draws', 3);
        $this->assertDatabaseCount('draw_groups', 3);
        $this->assertDatabaseCount('draw_group_registrations', 11);
        $this->assertDatabaseCount('fixtures', 15);
        $this->assertSame(0, Fixture::where('stage', '!=', 'RR')->count());
        $this->assertSame(0, Draw::where('published', true)->orWhere('oop_published', true)->count());
        $this->assertDatabaseCount('category_event_registrations', 11);
        $this->confirm($plan)->assertRedirect();
        $this->assertDatabaseCount('draws', 3);
        $this->assertDatabaseCount('fixtures', 15);
        $created = $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk();
        $this->export('created', $created->getContent());
    }

    public function test_rating_order_and_ball_pools_are_separate_without_fallback(): void
    {
        $this->entrants(4);
        $red = CategoryEvent::factory()->create(['event_id' => $this->event->id,
            'category_id' => Category::factory()->create(['name' => 'Red Ball Singles'])->id]);
        $this->entrants(4, $red, 'red');
        $orange = CategoryEvent::factory()->create(['event_id' => $this->event->id,
            'category_id' => Category::factory()->create(['name' => 'Orange Ball Singles'])->id]);
        $this->entrants(4, $orange, 'standard'); // Wrong pool must never substitute.
        $plan = $this->preview();
        $rows = collect($plan->proposal['rows']);
        $this->assertSame([90.0,89.0,88.0,87.0], $rows->where('category_id', $this->category->id)->pluck('score')->map(fn ($n) => (float) $n)->all());
        $this->assertSame([1,1,1,1], $rows->where('category_id', $red->id)->pluck('group')->all());
        $this->assertSame([null,null,null,null], $rows->where('category_id', $orange->id)->pluck('score')->all());
        $this->confirm($plan)->assertUnprocessable();
        $this->assertDatabaseCount('draws', 0);
    }

    public function test_seeds_follow_ctr_not_registration_creation_order(): void
    {
        $this->entrants(4);
        $keys = array_keys($this->snapshot['ball_ratings']['standard']['ratings']);
        foreach ($keys as $index => $id) $this->snapshot['ball_ratings']['standard']['ratings'][$id]['score'] = 20 + $index;
        $plan = $this->preview();
        $competition = app(CtrDrawPlanner::class)->competitions($plan->proposal)[0];
        $this->assertSame(array_reverse($keys), array_column($competition['rows'], 'player_id'));
        $this->confirm($plan)->assertRedirect();
        $actual = DB::table('draw_group_registrations')->orderBy('seed')->pluck('registration_id')->all();
        $this->assertSame(array_column($competition['rows'], 'registration_id'), $actual);
    }

    public function test_unrated_players_require_explicit_manual_review_and_can_be_placed(): void
    {
        $this->entrants(4, rated: false);
        $plan = $this->preview();
        $blocked = $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk()->assertSee('Assign every player');
        $this->export('blocked', $blocked->getContent());
        $placements = array_fill_keys(array_column($plan->proposal['rows'], 'entry_id'), 1);
        $body = ['token' => $plan->token, 'revision' => app(CtrDrawPlanner::class)->revision($plan), 'placements' => $placements];
        $this->postJson(route('backend.ctr-planner.review', $this->event), $body)->assertUnprocessable();
        $this->postJson(route('backend.ctr-planner.review', $this->event), $body + ['manual_reviewed' => true])->assertRedirect();
        $this->confirm($plan->fresh())->assertRedirect();
        $this->assertDatabaseCount('fixtures', 6);
    }

    public function test_stale_withdrawal_ratings_certification_and_existing_draw_changes_reject_creation(): void
    {
        $ids = $this->entrants(4);
        $plan = $this->preview();
        $this->snapshot['built_at'] = 'fictional-v2';
        $this->confirm($plan)->assertConflict();
        $plan = $this->preview();
        CategoryEventRegistration::whereKey($ids[0])->update(['withdrawn_at' => now()]);
        $this->confirm($plan)->assertConflict();
        CategoryEventRegistration::whereKey($ids[0])->update(['withdrawn_at' => null]);
        $plan = $this->preview();
        $existing = Draw::factory()->create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id]);
        $this->confirm($plan)->assertConflict();
        $existing->delete();
        $plan = $this->preview();
        $this->event->ctrCertification()->update(['certified' => false]);
        $this->confirm($plan)->assertForbidden();
        $this->assertDatabaseCount('fixtures', 0);
    }

    public function test_actor_event_and_assignment_boundaries_are_enforced(): void
    {
        $this->entrants(4);
        $plan = $this->preview();
        $outsider = User::factory()->create();
        $this->actingAs($outsider);
        $this->get(route('backend.ctr-planner.show', $this->event))->assertForbidden();
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $outsider->id]);
        $this->confirm($plan)->assertNotFound();
        $this->actingAs($this->actor);
        DB::table('event_admins')->where('user_id', $this->actor->id)->delete();
        $this->confirm($plan)->assertForbidden();
        $this->assertDatabaseCount('draws', 0);
    }

    public function test_certification_is_super_user_only_and_audited_without_publication(): void
    {
        $this->postJson(route('backend.ctr-planner.certify', $this->event), ['certified' => false])->assertForbidden();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actor->assignRole('super-user');
        $this->post(route('backend.ctr-planner.certify', $this->event), ['certified' => false])->assertRedirect();
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'event.ctr_certification_changed', 'subject_id' => $this->event->id, 'user_id' => $this->actor->id]);
        $this->assertFalse($this->event->ctrCertification()->first()->certified);
        $this->post(route('backend.ctr-planner.preview', $this->event), ['target' => 4])->assertForbidden();
    }

    public function test_existing_assignments_and_unpaid_entries_are_excluded(): void
    {
        $ids = $this->entrants(5);
        $existing = Draw::factory()->create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id]);
        $entry = CategoryEventRegistration::find($ids[0]);
        $existing->groups()->create(['name' => 'A'])->registrations()->attach($entry->registration_id);
        CategoryEventRegistration::whereKey($ids[1])->update(['payment_status_id' => 0]);
        $plan = $this->preview();
        $this->assertCount(3, $plan->proposal['rows']);
        $this->confirm($plan)->assertRedirect();
        $this->assertDatabaseCount('draws', 2);
        $this->assertSame(1, $existing->fresh()->groups()->first()->registrations()->count());
        $this->assertDatabaseCount('fixtures', 3);
    }

    public function test_mid_batch_failure_rolls_back_the_complete_programme(): void
    {
        $this->entrants(8);
        $plan = $this->preview();
        $calls = 0;
        $this->app->bind(RoundRobinGenerationService::class, function () use (&$calls) {
            if (++$calls === 2) throw new \RuntimeException('Fictional generation failure');
            return new RoundRobinGenerationService;
        });
        $this->withoutExceptionHandling();
        try { app(CtrDrawPlanner::class)->apply($this->event, $this->actor, $plan->token, app(CtrDrawPlanner::class)->revision($plan));
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) { $this->assertSame('Fictional generation failure', $e->getMessage()); }
        $this->assertDatabaseCount('draws', 0);
        $this->assertDatabaseCount('draw_groups', 0);
        $this->assertDatabaseCount('draw_group_registrations', 0);
        $this->assertDatabaseCount('fixtures', 0);
        $this->assertNull($plan->fresh()->applied_at);
    }

    public function test_convenor_can_create_new_programme_but_score_keeper_cannot(): void
    {
        $this->entrants(4);
        DB::table('event_admins')->where('user_id', $this->actor->id)->delete();
        DB::table('event_convenors')->insert(['event_id' => $this->event->id, 'user_id' => $this->actor->id, 'role' => 'convenor']);
        $plan = $this->preview();
        $this->confirm($plan)->assertRedirect();
        $scorer = User::factory()->create();
        DB::table('event_convenors')->insert(['event_id' => $this->event->id, 'user_id' => $scorer->id, 'role' => 'score-keeper']);
        $this->actingAs($scorer);
        $this->get(route('backend.ctr-planner.show', $this->event))->assertForbidden();
        $this->postJson(route('backend.ctr-planner.preview', $this->event), ['target' => 4])->assertForbidden();
    }

    public function test_expired_foreign_and_changed_review_tokens_cannot_apply(): void
    {
        $this->entrants(4);
        $plan = $this->preview();
        $other = Event::factory()->create(['eventType' => 1]);
        DB::table('event_admins')->insert(['event_id' => $other->id, 'user_id' => $this->actor->id]);
        CtrEventCertification::create(['event_id' => $other->id, 'certified' => true, 'updated_by' => $this->actor->id]);
        $body = ['token' => $plan->token, 'revision' => app(CtrDrawPlanner::class)->revision($plan)];
        $this->postJson(route('backend.ctr-planner.apply', $other), $body)->assertNotFound();
        $this->postJson(route('backend.ctr-planner.apply', $this->event), array_replace($body, ['revision' => str_repeat('0', 64)]))->assertConflict();
        $plan->update(['expires_at' => now()->subSecond()]);
        $this->confirm($plan)->assertConflict();
        $this->assertDatabaseCount('draws', 0);
    }

    public function test_duplicate_real_player_aliases_and_invalid_group_sizes_are_rejected(): void
    {
        $ids = $this->entrants(4);
        $first = CategoryEventRegistration::find($ids[0]);
        $second = CategoryEventRegistration::find($ids[1]);
        $second->registration->players()->sync($first->registration->players->modelKeys());
        $plan = $this->preview();
        $this->confirm($plan)->assertUnprocessable();
        $this->assertDatabaseCount('draws', 0);
        $placements = array_fill_keys(array_column($plan->proposal['rows'], 'entry_id'), 1);
        $placements[$ids[0]] = 2;
        $this->postJson(route('backend.ctr-planner.review', $this->event), [
            'token' => $plan->token, 'revision' => app(CtrDrawPlanner::class)->revision($plan), 'placements' => $placements,
        ])->assertUnprocessable();
    }

    private function export(string $state, string $html): void
    {
        if (getenv('CT_CTR_PLANNER_QA_HTML') !== '1') return;
        if (!app()->environment('testing') || DB::connection()->getDatabaseName() !== 'ct_testing') throw new \RuntimeException('Unsafe export environment');
        $dir = storage_path('app/testing/ctr-planner');
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($dir.'/'.$state.'.html', $html);
    }
}
