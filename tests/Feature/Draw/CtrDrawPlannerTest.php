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

    private function reviewPlan(CtrDrawPlan $plan, array $extra = []): CtrDrawPlan
    {
        $placements = array_column($plan->proposal['rows'], 'group', 'entry_id');
        $this->post(route('backend.ctr-planner.review', $this->event), array_replace([
            'token' => $plan->token, 'revision' => app(CtrDrawPlanner::class)->revision($plan), 'placements' => $placements,
        ], $extra))->assertRedirect();
        return $plan->fresh();
    }

    public function test_group_sizes_and_configurable_preference(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            foreach (['draw_groups', 'draw_group_registrations'] as $table) {
                $engine = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
                    ->where('TABLE_NAME', $table)->value('ENGINE');
                $this->assertSame('innodb', strtolower($engine));
            }
        }
        $planner = app(CtrDrawPlanner::class);
        foreach ([3 => [3], 4 => [4], 5 => [5], 6 => [3,3], 7 => [4,3], 8 => [4,4], 9 => [5,4], 10 => [4,3,3],
            11 => [4,4,3], 12 => [4,4,4], 13 => [5,4,4], 14 => [5,5,4], 15 => [5,5,5], 16 => [4,4,4,4]] as $n => $sizes) {
            $this->assertSame($sizes, $planner->sizes($n));
        }
        $this->assertSame([], $planner->sizes(2));
        $this->assertSame([3,3,3,3], $planner->sizes(12, 3));
        $this->assertSame([5,5], $planner->sizes(10, 5));
        $this->assertSame([5,5], $planner->sizes(10, 4, false));
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
        $short = $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk();
        $this->export('shortfall', $short->getContent());
        $this->confirm($plan)->assertUnprocessable();
        $plan = $this->reviewPlan($plan, ['short_matches_reviewed' => true]);
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
        $this->confirm($plan)->assertRedirect();
        $this->assertDatabaseCount('draws', 3);
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

    public function test_unrated_players_are_automatically_placed_without_rating_acknowledgement(): void
    {
        $this->entrants(4, rated: false);
        $plan = $this->preview();
        $this->assertSame([1,1,1,1], array_column($plan->proposal['rows'], 'group'));
        $this->assertSame([null,null,null,null], array_column($plan->proposal['rows'], 'score'));
        $this->assertFalse($plan->proposal['manual_reviewed']);
        $this->confirm($plan)->assertRedirect();
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
        $plan = $this->reviewPlan($plan, ['short_matches_reviewed' => true]);
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
        $this->entrants(6);
        DB::table('event_admins')->where('user_id', $this->actor->id)->delete();
        DB::table('event_convenors')->insert(['event_id' => $this->event->id, 'user_id' => $this->actor->id, 'role' => 'convenor']);
        $plan = $this->preview();
        $this->confirm($plan)->assertRedirect();
        $this->assertDatabaseCount('fixtures', 9);
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($this->actor)->allows('generateFixtures', Draw::first()));
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

    public function test_two_rated_and_two_unrated_form_one_group_with_nulls_at_bottom(): void
    {
        $this->entrants(2);
        $this->entrants(2, rated: false);
        $plan = $this->preview();
        $this->assertSame([1,1,1,1], array_column($plan->proposal['rows'], 'group'));
        $this->assertSame([90,89,null,null], array_column($plan->proposal['rows'], 'score'));
        $this->confirm($plan)->assertRedirect();
        $this->assertDatabaseCount('fixtures', 6);
    }

    public function test_eight_rated_and_one_unrated_are_five_plus_four_without_ability_fallback(): void
    {
        $this->entrants(8);
        $unrated = $this->entrants(1, rated: false);
        $plan = $this->preview();
        $groups = app(CtrDrawPlanner::class)->groups($plan->proposal);
        $this->assertSame([5,4], array_map(fn ($group) => count($group['rows']), $groups));
        $last = $groups[1]['rows'][3];
        $this->assertSame($unrated[0], $last['entry_id']);
        $this->assertNull($last['score']);
        $this->assertSame('Unrated', $last['rating_label']);
        $this->export('mixed', $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk()->getContent());
        $this->confirm($plan)->assertRedirect();
        $this->assertDatabaseCount('fixtures', 16);
    }

    public function test_eleven_unrated_are_all_included_and_only_the_match_shortfall_needs_review(): void
    {
        $this->entrants(11, rated: false);
        $plan = $this->preview();
        $this->assertCount(11, $plan->proposal['rows']);
        $this->assertSame([4,4,3], array_map(fn ($group) => count($group['rows']), app(CtrDrawPlanner::class)->groups($plan->proposal)));
        $this->assertFalse(collect($plan->proposal['rows'])->contains(fn ($row) => $row['score'] !== null));
        $this->confirm($plan)->assertUnprocessable()->assertJsonValidationErrors('groups');
        $plan = $this->reviewPlan($plan, ['short_matches_reviewed' => true]);
        $this->assertFalse($plan->proposal['manual_reviewed']);
        $this->export('unrated', $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk()->getContent());
        $this->confirm($plan)->assertRedirect();
        $this->assertDatabaseCount('draw_group_registrations', 11);
    }

    public function test_six_is_one_competition_with_exact_three_position_finals_and_two_prizes(): void
    {
        $this->entrants(6, rated: false);
        $plan = $this->preview();
        $competitions = app(CtrDrawPlanner::class)->competitions($plan->proposal);
        $this->assertCount(1, $competitions);
        $this->assertSame([3,3], array_map(fn ($group) => count($group['rows']), $competitions[0]['groups']));
        $this->assertSame(9, $competitions[0]['matches']);
        $this->export('six', $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk()->assertSee('= 2 prizes')->getContent());
        $this->confirm($plan)->assertRedirect();
        $this->assertDatabaseCount('draws', 1);
        $this->assertDatabaseCount('fixtures', 9);
        $this->assertSame(6, Fixture::where('stage', 'RR')->count());
        $finals = Fixture::where('stage', '!=', 'RR')->orderBy('registration1_source_position')->get();
        $this->assertSame([1,2,3], $finals->pluck('registration1_source_position')->map(fn ($n) => (int) $n)->all());
        $this->assertSame([1,2,3], $finals->pluck('registration2_source_position')->map(fn ($n) => (int) $n)->all());
        $this->assertFalse($finals->contains(fn ($fixture) => $fixture->registration1_id !== null || $fixture->registration2_id !== null));
        $this->assertSame(1, $finals->pluck('registration1_source_group_id')->unique()->count());
        $this->assertSame(1, $finals->pluck('registration2_source_group_id')->unique()->count());
    }

    public function test_ten_defaults_to_four_plus_shared_six_with_two_competitions_and_four_prizes(): void
    {
        $this->entrants(10);
        $plan = $this->preview();
        $planner = app(CtrDrawPlanner::class);
        $this->assertSame([4,3,3], array_map(fn ($group) => count($group['rows']), $planner->groups($plan->proposal)));
        $competitions = $planner->competitions($plan->proposal);
        $this->assertSame([false,true], array_column($competitions, 'six'));
        $this->assertSame([4,6], array_map(fn ($competition) => count($competition['rows']), $competitions));
        $ranked = array_column($plan->proposal['rows'], 'registration_id');
        $groups = $planner->groups($plan->proposal);
        $this->assertSame(array_slice($ranked, 0, 4), array_column($groups[0]['rows'], 'registration_id'));
        $this->assertSame([$ranked[4],$ranked[7],$ranked[8]], array_column($groups[1]['rows'], 'registration_id'));
        $this->assertSame([$ranked[5],$ranked[6],$ranked[9]], array_column($groups[2]['rows'], 'registration_id'));
        $this->export('ten', $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk()->assertSee('= 4 prizes')->getContent());
        $this->confirm($plan)->assertRedirect();
        $this->assertDatabaseCount('draws', 2);
        $this->assertDatabaseCount('draw_groups', 3);
        $this->assertDatabaseCount('fixtures', 15);
        $this->assertSame(3, Fixture::where('stage', '!=', 'RR')->count());
    }

    public function test_twenty_players_default_to_thirty_total_matches_three_each(): void
    {
        $this->entrants(20);
        $plan = $this->preview();
        $this->export('twenty', $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk()->getContent());
        $this->confirm($plan)->assertRedirect();
        $this->assertDatabaseCount('fixtures', 30);
        foreach (array_column($plan->proposal['rows'], 'registration_id') as $id) {
            $this->assertSame(3, Fixture::where(fn ($query) => $query->where('registration1_id', $id)->orWhere('registration2_id', $id))->count());
        }
    }

    public function test_small_category_is_visible_and_can_be_explicitly_excluded_without_changing_entries(): void
    {
        $this->entrants(2);
        $other = CategoryEvent::factory()->create(['event_id' => $this->event->id,
            'category_id' => Category::factory()->create(['name' => 'Red Ball Singles'])->id]);
        $this->entrants(4, $other, 'red');
        $plan = $this->preview();
        $this->assertSame([1,1], collect($plan->proposal['rows'])->where('category_id', $this->category->id)->pluck('group')->all());
        $this->export('blocked', $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk()->assertSee('Not enough players')->getContent());
        $this->confirm($plan)->assertUnprocessable();
        $before = CategoryEventRegistration::orderBy('id')->get()->map->getAttributes()->all();
        $plan = $this->reviewPlan($plan, ['excluded_categories' => [$this->category->id]]);
        $this->export('excluded2', $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk()->getContent());
        $this->confirm($plan)->assertRedirect();
        $this->assertDatabaseCount('draws', 1);
        $this->assertSame($other->id, Draw::first()->category_event_id);
        $this->assertSame($before, CategoryEventRegistration::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame([$this->category->id], \App\Models\DrawAuditLog::where('action', 'ctr_programme_created')->first()->payload['excluded_category_ids']);
    }

    public function test_exclusion_is_reversible_and_all_excluded_or_foreign_categories_are_rejected(): void
    {
        $this->entrants(4);
        $other = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $this->entrants(4, $other);
        $plan = $this->preview();
        $plan = $this->reviewPlan($plan, ['excluded_categories' => [$this->category->id]]);
        $this->assertCount(1, app(CtrDrawPlanner::class)->competitions($plan->proposal));
        $plan = $this->reviewPlan($plan, ['excluded_categories' => []]);
        $this->assertCount(2, app(CtrDrawPlanner::class)->competitions($plan->proposal));
        foreach ([[$this->category->id, $other->id], [999999]] as $excluded) {
            $this->postJson(route('backend.ctr-planner.review', $this->event), ['token' => $plan->token,
                'revision' => app(CtrDrawPlanner::class)->revision($plan), 'placements' => array_column($plan->proposal['rows'], 'group', 'entry_id'),
                'excluded_categories' => $excluded])->assertUnprocessable();
        }
        $this->export('categories', $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk()->getContent());
    }

    public function test_distinct_rating_components_are_not_ranked_together_and_mixing_needs_review(): void
    {
        $this->entrants(8);
        $keys = array_keys($this->snapshot['ball_ratings']['standard']['ratings']);
        foreach (array_slice($keys, 4) as $id) $this->snapshot['ball_ratings']['standard']['ratings'][$id]['component'] = 'separate';
        $plan = $this->preview();
        $groups = app(CtrDrawPlanner::class)->groups($plan->proposal);
        $this->assertCount(2, $groups);
        foreach ($groups as $group) $this->assertCount(1, array_unique(array_column($group['rows'], 'component')));
        $placements = array_column($plan->proposal['rows'], 'group', 'entry_id');
        $one = $groups[0]['rows'][0]['entry_id']; $two = $groups[1]['rows'][0]['entry_id'];
        [$placements[$one], $placements[$two]] = [$placements[$two], $placements[$one]];
        $body = ['token' => $plan->token, 'revision' => app(CtrDrawPlanner::class)->revision($plan), 'placements' => $placements];
        $this->postJson(route('backend.ctr-planner.review', $this->event), $body)->assertUnprocessable();
        $this->postJson(route('backend.ctr-planner.review', $this->event), $body + ['manual_reviewed' => true])->assertOk()->assertJsonPath('saved', true);
        $this->confirm($plan->fresh())->assertRedirect();
    }

    public function test_manual_mixed_bands_keep_unknowns_last_without_sorting_known_scores_across_scales(): void
    {
        $this->entrants(8);
        $unrated = $this->entrants(1, rated: false)[0];
        $keys = array_keys($this->snapshot['ball_ratings']['standard']['ratings']);
        foreach (array_slice($keys, 4) as $id) $this->snapshot['ball_ratings']['standard']['ratings'][$id]['component'] = 'separate';
        $this->snapshot['ball_ratings']['standard']['ratings'][$keys[4]]['score'] = 100;
        $plan = $this->preview();
        $planner = app(CtrDrawPlanner::class);
        $groups = $planner->groups($plan->proposal);
        $placements = array_column($plan->proposal['rows'], 'group', 'entry_id');
        $a = $groups[0]['rows'][0]['entry_id']; $b = $groups[1]['rows'][0]['entry_id'];
        [$placements[$a], $placements[$b]] = [$placements[$b], $placements[$a]];
        $placements[$unrated] = 1;
        $plan = $this->reviewPlan($plan, ['placements' => $placements, 'manual_reviewed' => true]);
        $group = $planner->groups($plan->proposal)[0];
        $this->assertSame([89,88,87,100,null], array_column($group['rows'], 'score'));
        $this->assertSame($unrated, $group['rows'][4]['entry_id']);
        $this->confirm($plan)->assertRedirect();
    }

    public function test_group_cards_and_seeds_are_numeric_and_old_versions_are_not_rewritten(): void
    {
        $this->entrants(11);
        $plan = $this->preview();
        $placements = array_column($plan->proposal['rows'], 'group', 'entry_id');
        foreach ($placements as &$group) if ($group === 3) $group = 10;
        unset($group);
        $plan = $this->reviewPlan($plan, ['placements' => $placements, 'short_matches_reviewed' => true]);
        $this->assertSame([1,2,10], array_column(app(CtrDrawPlanner::class)->groups($plan->proposal), 'number'));
        $this->export('sparse', $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk()->getContent());
        $proposal = $plan->proposal; $proposal['version'] = 1;
        $plan->update(['proposal' => $proposal, 'fingerprint' => str_repeat('0', 64)]);
        $this->confirm($plan)->assertConflict();
        $this->assertSame($proposal, $plan->fresh()->proposal);
        $this->assertDatabaseCount('draws', 0);
    }

    public function test_six_group_shape_and_exact_membership_cannot_be_bypassed_by_acknowledgements(): void
    {
        $this->entrants(6);
        $plan = $this->preview();
        $placements = array_column($plan->proposal['rows'], 'group', 'entry_id');
        $entry = array_search(2, $placements, true);
        $placements[$entry] = 1;
        $body = ['token' => $plan->token, 'revision' => app(CtrDrawPlanner::class)->revision($plan),
            'placements' => $placements, 'manual_reviewed' => true, 'short_matches_reviewed' => true];
        $this->postJson(route('backend.ctr-planner.review', $this->event), $body)->assertUnprocessable();
        unset($placements[$entry]);
        $this->postJson(route('backend.ctr-planner.review', $this->event), array_replace($body, ['placements' => $placements]))->assertUnprocessable();
        $this->assertDatabaseCount('draws', 0);
        $this->assertDatabaseCount('fixtures', 0);
        $this->assertCount(6, $plan->fresh()->proposal['rows']);
    }

    public function test_identity_and_unknown_ball_level_are_real_blockers_even_with_review_acknowledgements(): void
    {
        $ids = $this->entrants(4);
        $entry = CategoryEventRegistration::find($ids[0]);
        $extra = Player::factory()->create();
        $entry->registration->players()->attach($extra->id);
        $plan = $this->preview();
        $this->confirm($plan)->assertUnprocessable();
        $body = ['token' => $plan->token, 'revision' => app(CtrDrawPlanner::class)->revision($plan),
            'placements' => array_column($plan->proposal['rows'], 'group', 'entry_id'),
            'manual_reviewed' => true, 'short_matches_reviewed' => true];
        $this->postJson(route('backend.ctr-planner.review', $this->event), $body)->assertUnprocessable();
        $entry->registration->players()->detach($extra->id);
        $context = Mockery::mock(\App\Services\Performance\BallRatingContext::class);
        $context->shouldReceive('stage')->andReturn(null);
        $this->app->instance(\App\Services\Performance\BallRatingContext::class, $context);
        $plan = $this->preview();
        $this->confirm($plan)->assertUnprocessable();
        $this->assertDatabaseCount('draws', 0);
        $this->assertDatabaseCount('fixtures', 0);
        $this->assertDatabaseCount('category_event_registrations', 4);
    }

    public function test_later_six_player_final_preparation_failure_rolls_back_the_whole_batch(): void
    {
        $this->entrants(4);
        $other = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $this->entrants(6, $other);
        $plan = $this->preview();
        $this->app->bind(\App\Services\Draw\AutomaticPlayoffPreparationService::class, function () {
            $this->assertDatabaseCount('draws', 2);
            $this->assertDatabaseCount('fixtures', 12); // First RR draw and second RR have already been written.
            throw new \RuntimeException('Fictional final preparation failure');
        });
        try {
            app(CtrDrawPlanner::class)->apply($this->event, $this->actor, $plan->token, app(CtrDrawPlanner::class)->revision($plan));
            $this->fail('Expected failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Fictional final preparation failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('draws', 0);
        $this->assertDatabaseCount('draw_group_registrations', 0);
        $this->assertDatabaseCount('fixtures', 0);
        $this->assertDatabaseCount('category_event_registrations', 10);
    }

    public function test_realistic_version_one_preview_is_preserved_and_cannot_offer_confirmation(): void
    {
        $this->entrants(4, rated: false);
        $plan = $this->preview();
        $proposal = $plan->proposal;
        $proposal['version'] = 1;
        unset($proposal['straight_blocks'], $proposal['short_matches_reviewed'], $proposal['excluded_category_ids']);
        foreach ($proposal['rows'] as &$row) {
            $row['group'] = 0;
            $row['issue'] = 'CTR unavailable, stale or not comparable — place manually.';
            unset($row['band'], $row['rating_label'], $row['order']);
        }
        unset($row);
        $plan->update(['proposal' => $proposal, 'fingerprint' => str_repeat('0', 64)]);
        $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk()
            ->assertSee('earlier grouping rule')->assertDontSee('id="ctr-confirm"', false);
        $this->assertSame($proposal, $plan->fresh()->proposal);
        $this->confirm($plan)->assertConflict();
        $draw = Draw::factory()->create(['event_id' => $this->event->id]);
        $plan->update(['applied_at' => now(), 'draw_ids' => [$draw->id]]);
        $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk()->assertSee('Your draws are ready');
        $this->assertSame($proposal, $plan->fresh()->proposal);
    }

    public function test_live_shaped_orange_exclusion_is_saved_then_fresh_review_can_create_the_remaining_draws(): void
    {
        $this->entrants(12, rated: false);
        $orange = CategoryEvent::factory()->create(['event_id' => $this->event->id,
            'category_id' => Category::factory()->create(['name' => 'Orange Ball Singles'])->id]);
        $yellow = CategoryEvent::factory()->create(['event_id' => $this->event->id,
            'category_id' => Category::factory()->create(['name' => 'Yellow Ball Singles'])->id]);
        $this->entrants(2, $orange, 'orange', false);
        $this->entrants(6, $yellow, 'standard', false);
        $before = CategoryEventRegistration::orderBy('id')->get()->map->getAttributes()->all();
        $plan = $this->preview();
        $oldRevision = app(CtrDrawPlanner::class)->revision($plan);
        $initial = $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk()
            ->assertSee('Not enough players');
        $this->export('exclusion-flow-initial', $initial->getContent());
        $this->confirm($plan)->assertUnprocessable();
        $this->assertDatabaseCount('draws', 0);
        $plan = $this->reviewPlan($plan, ['excluded_categories' => [$orange->id]]);
        $reviewed = $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk()
            ->assertSee('= 8 prizes')->assertSee('4 competitions')->assertSee('27 matches');
        preg_match('/<button\b[^>]*\bid="ctr-confirm"[^>]*>/', $reviewed->getContent(), $button);
        $this->assertNotEmpty($button);
        $this->assertDoesNotMatchRegularExpression('/\bdisabled\b/', $button[0]);
        $this->assertSame([$orange->id], $plan->proposal['excluded_category_ids']);
        $this->assertCount(20, $plan->proposal['rows']);
        $this->assertFalse($plan->proposal['short_matches_reviewed']);
        $this->export('exclusion-flow-reviewed', $reviewed->getContent());
        $this->postJson(route('backend.ctr-planner.apply', $this->event), ['token' => $plan->token,
            'revision' => $oldRevision])->assertConflict();
        $this->confirm($plan)->assertRedirect();
        $this->assertDatabaseCount('draws', 4);
        $this->assertDatabaseCount('fixtures', 27);
        $this->assertSame(0, Draw::where('category_event_id', $orange->id)->count());
        $this->assertSame(0, DB::table('draw_group_registrations')->whereIn('registration_id',
            CategoryEventRegistration::where('category_event_id', $orange->id)->pluck('registration_id'))->count());
        $this->assertSame($before, CategoryEventRegistration::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame(18, DB::table('draw_group_registrations')->count());
        $this->confirm($plan)->assertRedirect();
        $this->assertDatabaseCount('draws', 4);
    }

    public function test_ajax_exclusion_saves_exact_revision_and_totals_without_creating_draws(): void
    {
        $this->entrants(12, rated: false);
        $orange = CategoryEvent::factory()->create(['event_id' => $this->event->id,
            'category_id' => Category::factory()->create(['name' => 'Orange Ball Singles'])->id]);
        $yellow = CategoryEvent::factory()->create(['event_id' => $this->event->id,
            'category_id' => Category::factory()->create(['name' => 'Yellow Ball Singles'])->id]);
        $this->entrants(2, $orange, 'orange', false);
        $this->entrants(6, $yellow, 'standard', false);
        $before = CategoryEventRegistration::orderBy('id')->get()->map->getAttributes()->all();
        $plan = $this->preview();
        $planner = app(CtrDrawPlanner::class);
        $body = ['token' => $plan->token, 'revision' => $planner->revision($plan),
            'placements' => array_column($plan->proposal['rows'], 'group', 'entry_id'), 'excluded_categories' => [$orange->id]];
        $this->export('exclusion-flow-initial', $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk()->getContent());
        $response = $this->postJson(route('backend.ctr-planner.review', $this->event), $body)->assertOk()
            ->assertJsonPath('saved', true)->assertJsonPath('ready', true)->assertJsonPath('token', $plan->token)
            ->assertJsonPath('event_id', $this->event->id)->assertJsonPath('excluded_category_ids', [$orange->id])
            ->assertJsonPath('totals', ['competitions' => 4, 'matches' => 27, 'included_players' => 18, 'winner_prizes' => 4, 'sportsmanship_prizes' => 4]);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $plan->refresh();
        $this->assertSame($planner->revision($plan), $response->json('revision'));
        $this->assertNotSame($body['revision'], $response->json('revision'));
        $this->export('exclusion-flow-reviewed', $response->getContent(), 'json');
        $this->export('exclusion-flow-reviewed', $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk()->getContent());
        $this->assertDatabaseCount('draws', 0);
        $this->assertDatabaseCount('fixtures', 0);
        $this->assertSame($before, CategoryEventRegistration::orderBy('id')->get()->map->getAttributes()->all());
        $accepted = $response->json('revision');
        // An older request cannot overwrite the newly saved exclusions.
        $this->postJson(route('backend.ctr-planner.review', $this->event), $body)->assertConflict();
        $this->assertSame($accepted, $planner->revision($plan->fresh()));
        // Re-including the two-player field is an invalid intermediate edit, not a save.
        $this->postJson(route('backend.ctr-planner.review', $this->event), array_replace($body, ['revision' => $accepted, 'excluded_categories' => []]))
            ->assertUnprocessable();
        $this->assertSame($accepted, $planner->revision($plan->fresh()));
        $this->assertSame([$orange->id], $plan->fresh()->proposal['excluded_category_ids']);
        // A later valid move uses the accepted revision and returns the next revision.
        $green = collect($plan->proposal['rows'])->where('category_id', $this->category->id);
        $a = $green->firstWhere('group', 1)['entry_id']; $b = $green->firstWhere('group', 2)['entry_id'];
        [$body['placements'][$a], $body['placements'][$b]] = [$body['placements'][$b], $body['placements'][$a]];
        $later = $this->postJson(route('backend.ctr-planner.review', $this->event), array_replace($body, ['revision' => $accepted]))->assertOk();
        $this->assertNotSame($accepted, $later->json('revision'));
        $this->assertSame($planner->revision($plan->fresh()), $later->json('revision'));
        $this->assertDatabaseCount('draws', 0);
        $this->assertDatabaseCount('fixtures', 0);
        $this->post(route('backend.ctr-planner.apply', $this->event), ['token' => $plan->token,
            'revision' => $later->json('revision')])->assertRedirect();
        $this->assertDatabaseCount('draws', 4);
        $this->assertDatabaseCount('fixtures', 27);
        $this->assertSame($before, CategoryEventRegistration::orderBy('id')->get()->map->getAttributes()->all());
    }

    public function test_ajax_save_keeps_actor_and_source_boundaries(): void
    {
        $this->entrants(4);
        $plan = $this->preview();
        $body = ['token' => $plan->token, 'revision' => app(CtrDrawPlanner::class)->revision($plan),
            'placements' => array_column($plan->proposal['rows'], 'group', 'entry_id')];
        $this->snapshot['built_at'] = 'changed-while-editing';
        $this->postJson(route('backend.ctr-planner.review', $this->event), $body)->assertConflict();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->postJson(route('backend.ctr-planner.review', $this->event), $body)->assertForbidden();
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $outsider->id]);
        $this->postJson(route('backend.ctr-planner.review', $this->event), $body)->assertNotFound();
        $this->assertSame($body['revision'], app(CtrDrawPlanner::class)->revision($plan->fresh()));
        $this->assertDatabaseCount('draws', 0);
    }

    public function test_six_default_snake_uses_rank_one_four_five_and_two_three_six_with_matching_seeds(): void
    {
        $this->entrants(6);
        $plan = $this->preview();
        $ranked = array_column($plan->proposal['rows'], 'registration_id');
        $expected = [[$ranked[0],$ranked[3],$ranked[4]], [$ranked[1],$ranked[2],$ranked[5]]];
        $groups = app(CtrDrawPlanner::class)->groups($plan->proposal);
        $this->assertSame($expected, array_map(fn ($group) => array_column($group['rows'], 'registration_id'), $groups));
        $this->confirm($plan)->assertRedirect();
        foreach (Draw::first()->groups()->orderBy('name')->get() as $index => $group) {
            $this->assertSame($expected[$index], $group->groupRegistrations()->orderBy('seed')->pluck('registration_id')->map(fn ($id) => (int) $id)->all());
        }
        $this->assertDatabaseCount('fixtures', 9);
    }

    public function test_snake_unknown_slots_and_manual_ajax_moves_do_not_reseed(): void
    {
        $this->entrants(2);
        $this->entrants(4, rated: false);
        $plan = $this->preview();
        $rows = $plan->proposal['rows'];
        $this->assertSame([1,2,2,1,1,2], array_column($rows, 'group'));
        $this->assertSame([90,89,null,null,null,null], array_column($rows, 'score'));
        $placements = array_column($rows, 'group', 'entry_id');
        [$placements[$rows[0]['entry_id']], $placements[$rows[1]['entry_id']]] = [2,1];
        $body = ['token' => $plan->token, 'revision' => app(CtrDrawPlanner::class)->revision($plan), 'placements' => $placements];
        $response = $this->postJson(route('backend.ctr-planner.review', $this->event), $body)->assertOk()->assertJsonPath('ready', true);
        $this->assertSame($placements, array_column($plan->fresh()->proposal['rows'], 'group', 'entry_id'));
        $this->postJson(route('backend.ctr-planner.review', $this->event), array_replace($body, ['revision' => $response->json('revision')]))->assertOk();
        $this->assertSame($placements, array_column($plan->fresh()->proposal['rows'], 'group', 'entry_id'));
    }

    public function test_snake_ties_are_deterministic_and_incomparable_linked_bands_require_review(): void
    {
        $this->entrants(6);
        foreach ($this->snapshot['ball_ratings']['standard']['ratings'] as &$rating) $rating['score'] = 50;
        unset($rating);
        $first = $this->preview();
        $second = $this->preview();
        $this->assertSame($first->proposal, $second->proposal);
        $keys = array_keys($this->snapshot['ball_ratings']['standard']['ratings']);
        foreach (array_slice($keys, 3) as $id) $this->snapshot['ball_ratings']['standard']['ratings'][$id]['component'] = 'separate';
        $plan = $this->preview();
        $this->assertSame([1,1,1,2,2,2], array_column($plan->proposal['rows'], 'group'));
        $this->confirm($plan)->assertUnprocessable();
        $response = $this->postJson(route('backend.ctr-planner.review', $this->event), ['token' => $plan->token,
            'revision' => app(CtrDrawPlanner::class)->revision($plan), 'placements' => array_column($plan->proposal['rows'], 'group', 'entry_id'),
            'manual_reviewed' => true])->assertOk();
        $this->assertSame($response->json('revision'), app(CtrDrawPlanner::class)->revision($plan->fresh()));
    }

    public function test_version_two_adjacent_seed_preview_is_stale_and_never_reordered_by_new_page(): void
    {
        $this->entrants(6);
        $plan = $this->preview();
        $old = $plan->proposal;
        $old['version'] = 2;
        foreach ($old['rows'] as $index => &$row) $row['group'] = $index < 3 ? 1 : 2;
        unset($row);
        $plan->update(['proposal' => $old, 'fingerprint' => str_repeat('0', 64)]);
        $this->get(route('backend.ctr-planner.show', [$this->event, 'plan' => $plan->token]))->assertOk()
            ->assertSee('earlier grouping rule')->assertDontSee('id="ctr-placement-form"', false);
        $this->assertSame($old, $plan->fresh()->proposal);
        $this->postJson(route('backend.ctr-planner.review', $this->event), ['token' => $plan->token,
            'revision' => app(CtrDrawPlanner::class)->revision($plan), 'placements' => array_column($old['rows'], 'group', 'entry_id')])->assertConflict();
        $this->assertSame($old, $plan->fresh()->proposal);
    }

    private function export(string $state, string $html, string $extension = 'html'): void
    {
        if (getenv('CT_CTR_PLANNER_QA_HTML') !== '1') return;
        $safe = DB::connection()->getDatabaseName() === 'ct_testing'
            || (DB::connection()->getDriverName() === 'sqlite' && DB::connection()->getDatabaseName() === ':memory:');
        if (!app()->environment('testing') || !$safe) throw new \RuntimeException('Unsafe export environment');
        $dir = storage_path('app/testing/ctr-planner');
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($dir.'/'.$state.'.'.$extension, $html);
    }
}
