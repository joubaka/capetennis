<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, Event, EventAdmin, NoProfileTeamPlayer, Player, PracticeCampPlan, Team, TeamPlayer, TeamRegion, TeamSelectionImport, TeamSelectionInvitation, User};
use App\Services\PracticeCampPlanner;
use Illuminate\Support\Facades\{Bus, DB, Mail, Schema};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PracticeCampPlannerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        (require database_path('migrations/2013_01_01_000000_create_base_schema_for_testing.php'))->up();
        Schema::table('players', function ($t) {
            $t->char('identity_name_hash', 64)->nullable();
            $t->char('identity_email_dob_hash', 64)->nullable();
            $t->char('identity_cell_dob_hash', 64)->nullable();
        });
        Schema::table('category_event_registrations', fn ($t) => $t->softDeletes());
        (require database_path('migrations/2026_11_20_120000_create_practice_camp_plans.php'))->up();
        Schema::create('eventtypes', function ($t) { $t->id(); $t->string('name'); $t->integer('type'); });
        DB::table('eventtypes')->insert([['id' => 1, 'name' => 'Individual', 'type' => 1], ['id' => 6, 'name' => 'Camp', 'type' => 3]]);
        Schema::create('team_regions', function ($t) { $t->id(); $t->string('region_name'); $t->timestamps(); });
        Schema::create('event_regions', function ($t) { $t->id(); $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('region_id'); $t->integer('ordering')->nullable(); });
        Schema::table('teams', function ($t) { $t->unsignedBigInteger('region_id')->nullable(); $t->unsignedBigInteger('category_event_id')->nullable(); $t->boolean('noProfile')->default(false); $t->boolean('published')->default(true); });
        Schema::table('team_players', fn ($t) => $t->integer('rank')->default(1));
        Schema::create('no_profile_team_players', function ($t) { $t->id(); $t->unsignedBigInteger('team_id'); $t->unsignedBigInteger('player_profile')->nullable(); $t->string('name'); $t->string('surname'); $t->integer('rank')->default(1); $t->timestamps(); });
        Schema::create('team_selection_imports', function ($t) { $t->id(); $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('region_id'); $t->string('status'); $t->timestamps(); });
        Schema::create('team_selection_invitations', function ($t) { $t->id(); $t->unsignedBigInteger('import_id'); $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('region_id'); $t->unsignedBigInteger('team_id'); $t->unsignedBigInteger('player_id'); $t->string('status'); $t->integer('ranking_position')->nullable(); $t->json('snapshot_json')->nullable(); $t->timestamps(); });
        Schema::create('event_mail_issues', function ($t) { $t->id(); $t->unsignedBigInteger('user_id'); $t->timestamp('read_at')->nullable(); });
        Schema::create('site_settings', function ($t) { $t->id(); $t->string('key')->unique(); $t->text('value')->nullable(); $t->timestamps(); });
        Mail::fake();
        Bus::fake();
    }

    private function superUser(): User
    {
        Role::findOrCreate('super-user', 'web');

        return User::factory()->create()->assignRole('super-user');
    }

    private function admin(Event ...$events): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create()->assignRole('admin');
        foreach ($events as $event) {
            EventAdmin::create(['event_id' => $event->id, 'user_id' => $user->id]);
        }

        return $user;
    }

    private function source(?Event $event = null, ?TeamRegion $region = null, bool $imported = false): array
    {
        $event ??= Event::factory()->create(['name' => 'Fictional District Teams 2026']);
        $region ??= TeamRegion::create(['region_name' => 'Example Region']);
        DB::table('event_regions')->insertOrIgnore(['event_id' => $event->id, 'region_id' => $region->id]);
        $category = Category::create(['name' => 'U10 Boys']);
        $division = CategoryEvent::create(['event_id' => $event->id, 'category_id' => $category->id]);
        $team = Team::create(['name' => 'Example U10 Boys', 'region_id' => $region->id, 'category_event_id' => $division->id, 'noProfile' => $imported]);

        return [$event, $region, $team];
    }

    private function payload(PracticeCampPlan $plan, User $actor, Event $source, TeamRegion $region, array $teams, ?array $keys = null): array
    {
        $preview = app(PracticeCampPlanner::class)->preview($actor, $source, $region->id, $teams);

        return ['revision' => $plan->fresh()->revision, 'source_event_id' => $source->id, 'region_id' => $region->id,
            'team_ids' => $teams, 'selected' => $keys ?? array_column($preview['rows'], 'key'), 'fingerprint' => $preview['fingerprint']];
    }

    public function test_creation_is_idempotent_private_and_has_provisional_unknown_details(): void
    {
        $user = $this->superUser();
        $this->get(route('backend.practice-camps.index'))->assertRedirect();
        $this->actingAs($user)->post(route('backend.practice-camps.store'))->assertRedirect();
        $this->post(route('backend.practice-camps.store'))->assertRedirect();
        $plan = PracticeCampPlan::firstOrFail();
        $event = $plan->event;
        $this->assertDatabaseCount('practice_camp_plans', 1);
        $this->assertDatabaseCount('events', 1);
        $this->assertSame('2027-01-07', $event->start_date->toDateString());
        $this->assertSame('2027-01-09', $event->end_date->toDateString());
        $this->assertFalse($event->published);
        $this->assertFalse($event->signUp);
        $this->assertFalse($event->registrationIsOpen());
        $this->assertTrue($plan->dates_provisional);
        $this->assertNull($plan->entry_fee);
        $this->assertNull($plan->capacity);
        $this->assertNull($event->email);
        $this->assertSame(6, (int) $event->eventType);
        $this->get(route('backend.practice-camps.index'))->assertOk()->assertSee('Create or open the private 2027 draft');
        $this->get(route('backend.practice-camps.show', $plan))->assertOk()->assertSee('Fee not set')->assertSee('No players selected yet.');
        $this->actingAs($this->admin())->post(route('backend.practice-camps.store'))->assertForbidden();
        $this->assertNoConsequentialWrites();
    }

    public function test_source_and_target_authorization_and_cross_event_team_isolation(): void
    {
        $super = $this->superUser();
        $plan = app(PracticeCampPlanner::class)->create2027($super);
        [$source, $region, $team] = $this->source();
        [$foreign, , $foreignTeam] = $this->source(null, $region);
        $player = Player::factory()->create();
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1]);
        $url = route('backend.practice-camps.show', $plan);
        $query = ['source_event_id' => $source->id, 'region_id' => $region->id, 'team_ids' => [$team->id], 'preview' => 1];
        $payload = $this->payload($plan, $super, $source, $region, [$team->id]);
        $this->actingAs($this->admin($plan->event))->get($url.'?'.http_build_query($query))->assertForbidden();
        $this->post(route('backend.practice-camps.add', $plan), $payload)->assertForbidden();
        $this->actingAs($this->admin($source))->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs($super)->get($url.'?'.http_build_query([...$query, 'team_ids' => [$foreignTeam->id]]))->assertNotFound();
        $otherRegion = TeamRegion::create(['region_name' => 'Unrelated region']);
        $this->get($url.'?'.http_build_query([...$query, 'region_id' => $otherRegion->id]))->assertNotFound();
        $this->post(route('backend.practice-camps.add', $plan), $payload)->assertRedirect();
        $targetOnly = $this->admin($plan->event);
        $this->actingAs($targetOnly)->get($url)->assertForbidden();
        $this->delete(route('backend.practice-camps.remove', $plan), ['revision' => $plan->fresh()->revision, 'key' => 'player:'.$player->id])->assertForbidden();
        $this->assertCount(1, $plan->fresh()->candidates);
    }

    public function test_draft_deduplicates_players_preserves_sources_and_does_not_merge_imported_identity(): void
    {
        $super = $this->superUser();
        $plan = app(PracticeCampPlanner::class)->create2027($super);
        [$source, $region, $team] = $this->source();
        [, , $second] = $this->source($source, $region);
        [, , $imported] = $this->source($source, $region, true);
        $player = Player::factory()->create(['name' => 'Fictional', 'surname' => 'Player']);
        foreach ([$team, $second] as $t) {
            TeamPlayer::create(['team_id' => $t->id, 'player_id' => $player->id, 'rank' => 1]);
        }
        $slot = NoProfileTeamPlayer::create(['team_id' => $imported->id, 'name' => 'Fictional', 'surname' => 'Player', 'player_profile' => $player->id, 'rank' => 1]);
        $this->actingAs($super);
        $url = route('backend.practice-camps.add', $plan);
        $this->post($url, $this->payload($plan, $super, $source, $region, [$team->id]))->assertRedirect();
        $this->post($url, $this->payload($plan, $super, $source, $region, [$second->id, $imported->id]))->assertRedirect();
        $rows = collect($plan->fresh()->candidates)->keyBy('key');
        $this->assertCount(2, $rows);
        $this->assertCount(2, $rows['player:'.$player->id]['sources']);
        $this->assertNull($rows['imported:'.$imported->id.':'.$slot->id]['player_id']);
        $this->post($url, $this->payload($plan, $super, $source, $region, [$team->id]))->assertRedirect();
        $this->assertCount(2, $plan->fresh()->candidates);
        $this->assertNoConsequentialWrites();
    }

    public function test_stale_drafts_changed_rosters_and_forged_keys_are_rejected(): void
    {
        $super = $this->superUser();
        $plan = app(PracticeCampPlanner::class)->create2027($super);
        [$source, $region, $team] = $this->source();
        $player = Player::factory()->create();
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1]);
        $payload = $this->payload($plan, $super, $source, $region, [$team->id]);
        $this->actingAs($super);
        $url = route('backend.practice-camps.add', $plan);
        $this->post($url, [...$payload, 'selected' => ['player:999999']])->assertUnprocessable();
        DB::table('teams')->where('id', $team->id)->update(['name' => 'Changed team']);
        $this->post($url, $payload)->assertConflict();
        $fresh = $this->payload($plan, $super, $source, $region, [$team->id]);
        $this->post($url, $fresh)->assertRedirect();
        $this->post($url, $fresh)->assertConflict();
        $this->delete(route('backend.practice-camps.remove', $plan), ['revision' => $fresh['revision'], 'key' => 'player:'.$player->id])->assertConflict();
        $this->delete(route('backend.practice-camps.remove', $plan), ['revision' => $plan->fresh()->revision, 'key' => 'player:'.$player->id])->assertRedirect();
        $this->assertSame([], $plan->fresh()->candidates);
    }

    public function test_declines_are_review_only_manual_rank_is_not_presented_as_merit_and_old_campaigns_are_excluded(): void
    {
        $super = $this->superUser();
        $plan = app(PracticeCampPlanner::class)->create2027($super);
        [$source, $region, $team] = $this->source();
        [$old, $declined, $manual] = Player::factory()->count(3)->create()->all();
        $oldImport = TeamSelectionImport::create(['event_id' => $source->id, 'region_id' => $region->id, 'status' => 'sent']);
        $newImport = TeamSelectionImport::create(['event_id' => $source->id, 'region_id' => $region->id, 'status' => 'sent']);
        foreach ([[$oldImport, $old, 1, []], [$newImport, $declined, 2, ['ranking_position' => 2]], [$newImport, $manual, 99, ['selection_source' => 'manual_system_profile', 'ranking_position' => null]]] as [$import, $player, $rank, $snapshot]) {
            TeamSelectionInvitation::create(['event_id' => $source->id, 'region_id' => $region->id, 'team_id' => $team->id,
                'import_id' => $import->id, 'player_id' => $player->id, 'status' => 'declined', 'ranking_position' => $rank, 'snapshot_json' => $snapshot]);
        }
        $preview = app(PracticeCampPlanner::class)->preview($super, $source, $region->id, [$team->id]);
        $rows = collect($preview['rows'])->keyBy('key');
        $this->assertCount(2, $rows);
        $this->assertFalse($rows->has('player:'.$old->id));
        $this->assertSame(2, $rows['player:'.$declined->id]['sources'][0]['original_rank']);
        $this->assertNull($rows['player:'.$manual->id]['sources'][0]['original_rank']);
        $response = $this->actingAs($super)->get(route('backend.practice-camps.show', $plan).'?'.http_build_query([
            'source_event_id' => $source->id, 'region_id' => $region->id, 'team_ids' => [$team->id], 'preview' => 1,
        ]))->assertOk()->assertSee('manually selected; ranking unavailable')->assertSee('Declined — eligibility needs review');
        if (getenv('CAMP_RENDER_FIXTURE')) {
            $dir = storage_path('framework/testing');
            if (! is_dir($dir)) mkdir($dir, 0777, true);
            file_put_contents($dir.'/practice-camp-planner.html', $response->getContent());
        }
        $this->assertNoConsequentialWrites();
    }

    private function assertNoConsequentialWrites(): void
    {
        foreach (['registrations', 'category_event_registrations', 'registration_orders', 'team_payment_orders', 'wallet_transactions', 'user_players'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Bus::assertNothingDispatched();
    }
}
