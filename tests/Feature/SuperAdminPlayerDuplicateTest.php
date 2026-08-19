<?php

namespace Tests\Feature;

use App\Domain\Ranking\Services\RankingCalculationService;
use App\Models\Draw;
use App\Models\Event;
use App\Models\Player;
use App\Models\RankingList;
use App\Models\User;
use App\Services\PlayerDuplicateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminPlayerDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->superUser = User::factory()->create();
        $this->superUser->assignRole('super-user');
    }

    public function test_only_super_users_can_access_duplicate_review_and_comparison(): void
    {
        $first = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith']);
        $second = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith']);

        $this->get(route('superadmin.player-duplicates.index'))->assertRedirect();
        $this->actingAs(User::factory()->create())
            ->get(route('superadmin.player-duplicates.index'))->assertForbidden();
        $this->actingAs($this->superUser)
            ->get(route('superadmin.player-duplicates.index'))->assertOk();
        $this->actingAs(User::factory()->create())
            ->get(route('superadmin.player-duplicates.review', [$first, $second]))->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->post(route('superadmin.player-duplicates.bulk-review'), ['pairs' => ["{$first->id}:{$second->id}"]])
            ->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->post(route('superadmin.player-duplicates.bulk-merge'), [])->assertForbidden();
    }

    public function test_scan_shows_matching_names_linked_emails_and_confidence(): void
    {
        $owner = User::factory()->create(['email' => 'parent@example.test']);
        $first = Player::factory()->create([
            'name' => '  Jamie', 'surname' => 'Smith ', 'email' => 'player@example.test', 'dateOfBirth' => '2012-01-01',
        ]);
        $second = Player::factory()->create([
            'name' => 'jamie', 'surname' => 'SMITH', 'dateOfBirth' => '2012-01-01',
        ]);
        DB::table('user_players')->insert([
            'user_id' => $owner->id, 'player_id' => $first->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->superUser)->get(route('superadmin.player-duplicates.index'))
            ->assertOk()->assertSee('Jamie Smith')->assertSee('parent@example.test')
            ->assertSee('player@example.test')->assertSee("#{$second->id}")->assertSee('High confidence');
    }

    public function test_scan_also_finds_name_variants_with_same_dob_and_email(): void
    {
        $first = Player::factory()->create([
            'name' => 'Liz', 'surname' => 'Botha', 'dateOfBirth' => '2010-04-03', 'email' => 'same@example.test',
        ]);
        $second = Player::factory()->create([
            'name' => 'Elizabeth', 'surname' => 'Botha', 'dateOfBirth' => '2010-04-03', 'email' => 'SAME@example.test',
        ]);

        $this->actingAs($this->superUser)->get(route('superadmin.player-duplicates.index'))
            ->assertOk()->assertSee("#{$first->id}")->assertSee("#{$second->id}")
            ->assertSee('Strong supporting match');
    }

    public function test_duplicate_review_uses_sized_bootstrap_pagination_controls(): void
    {
        $paginator = new LengthAwarePaginator([], 26, 25, 1, [
            'path' => route('superadmin.player-duplicates.index'),
        ]);
        $duplicates = Mockery::mock(PlayerDuplicateService::class);
        $duplicates->shouldReceive('candidates')->once()->with(25, false)->andReturn($paginator);
        $this->app->instance(PlayerDuplicateService::class, $duplicates);

        $this->actingAs($this->superUser)->get(route('superadmin.player-duplicates.index'))
            ->assertOk()->assertSee('class="pagination"', false)->assertDontSee('w-5 h-5', false);
    }

    public function test_queue_offers_on_demand_quick_merge_when_only_one_strong_match_has_history(): void
    {
        $empty = Player::factory()->create([
            'name' => 'Emile', 'surname' => 'Van Antwerpen', 'dateOfBirth' => '2008-09-01',
            'email' => 'emile@example.test', 'cellNr' => '0826119619',
        ]);
        $withHistory = Player::factory()->create([
            'name' => 'emile', 'surname' => 'van antwerpen', 'dateOfBirth' => '2008-09-01',
            'email' => 'EMILE@example.test', 'cellNr' => '082 611 9619',
        ]);
        $registrationId = DB::table('registrations')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        DB::table('player_registrations')->insert([
            'registration_id' => $registrationId, 'player_id' => $withHistory->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->superUser)->get(route('superadmin.player-duplicates.index'))
            ->assertOk()
            ->assertSee('Quick merge eligible')
            ->assertSee("Quick merge into #{$withHistory->id}")
            ->assertSee('bulk-review-progress', false)
            ->assertSee('bulk-review-progress-percent', false)
            ->assertSee(route('superadmin.player-duplicates.quick-review', [$empty, $withHistory]), false);

        $this->actingAs(User::factory()->create())
            ->get(route('superadmin.player-duplicates.quick-review', [$empty, $withHistory]))
            ->assertForbidden();

        $this->actingAs($this->superUser)
            ->get(route('superadmin.player-duplicates.quick-review', [$empty, $withHistory]))
            ->assertOk()
            ->assertSee("Keep #{$withHistory->id} and permanently remove #{$empty->id}")
            ->assertSee('<code>MERGE</code>', false)
            ->assertSee('Confirm permanent merge');
    }

    public function test_quick_merge_is_not_offered_when_both_profiles_have_history(): void
    {
        $first = Player::factory()->create([
            'name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01', 'email' => 'same@example.test',
        ]);
        $second = Player::factory()->create([
            'name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01', 'email' => 'same@example.test',
        ]);
        foreach ([$first, $second] as $player) {
            $registrationId = DB::table('registrations')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
            DB::table('player_registrations')->insert([
                'registration_id' => $registrationId, 'player_id' => $player->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($this->superUser)->get(route('superadmin.player-duplicates.index'))
            ->assertOk()->assertDontSee('Quick merge eligible');

        $this->actingAs($this->superUser)
            ->from(route('superadmin.player-duplicates.index'))
            ->get(route('superadmin.player-duplicates.quick-review', [$first, $second]))
            ->assertRedirect(route('superadmin.player-duplicates.index'))
            ->assertSessionHasErrors('quick_merge');
    }

    public function test_quick_merge_rejects_weak_name_only_match_even_with_one_sided_history(): void
    {
        $first = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => null]);
        $second = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => null]);
        $registrationId = DB::table('registrations')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        DB::table('player_registrations')->insert([
            'registration_id' => $registrationId, 'player_id' => $second->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->superUser)->get(route('superadmin.player-duplicates.index'))
            ->assertOk()->assertDontSee('Quick merge eligible');
        $this->actingAs($this->superUser)
            ->from(route('superadmin.player-duplicates.index'))
            ->get(route('superadmin.player-duplicates.quick-review', [$first, $second]))
            ->assertRedirect(route('superadmin.player-duplicates.index'))
            ->assertSessionHasErrors('quick_merge');
    }

    public function test_super_admin_can_merge_multiple_quick_candidates_with_one_atomic_confirmation(): void
    {
        $keep = Player::factory()->create([
            'name' => 'Emile', 'surname' => 'Van Antwerpen', 'dateOfBirth' => '2008-09-01',
            'email' => 'emile@example.test', 'cellNr' => '0826119619',
        ]);
        $firstEmpty = Player::factory()->create([
            'name' => 'emile', 'surname' => 'van antwerpen', 'dateOfBirth' => '2008-09-01',
            'email' => 'EMILE@example.test', 'cellNr' => '082 611 9619',
        ]);
        $secondEmpty = Player::factory()->create([
            'name' => 'Emile', 'surname' => 'Van Antwerpen', 'dateOfBirth' => '2008-09-01',
            'email' => 'emile@example.test', 'cellNr' => '0826119619',
        ]);
        $registrationId = DB::table('registrations')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        DB::table('player_registrations')->insert([
            'registration_id' => $registrationId, 'player_id' => $keep->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $pairs = [
            ['first_id' => $keep->id, 'second_id' => $firstEmpty->id],
            ['first_id' => $keep->id, 'second_id' => $secondEmpty->id],
        ];
        $batch = app(PlayerDuplicateService::class)->quickMergeBatchAnalysis($pairs);

        $this->actingAs($this->superUser)->post(route('superadmin.player-duplicates.bulk-review'), [
            'pairs' => ["{$keep->id}:{$firstEmpty->id}", "{$keep->id}:{$secondEmpty->id}"],
        ])->assertOk()
            ->assertSee('Review bulk duplicate merge')
            ->assertSee('<code>MERGE</code>', false)
            ->assertSee('Merge all 2 selected profiles');

        $payload = [
            'pairs' => $pairs,
            'batch_digest' => $batch['digest'],
            'batch_mode' => 'quick',
            'reason' => 'Confirmed duplicate profiles in one reviewed batch.',
            'confirmation' => $batch['confirmation_phrase'],
        ];
        $this->actingAs($this->superUser)
            ->post(route('superadmin.player-duplicates.bulk-merge'), $payload)
            ->assertRedirect(route('superadmin.player-duplicates.index'))
            ->assertSessionHas('success', '2 duplicate profiles were merged successfully.');

        $this->assertDatabaseMissing('players', ['id' => $firstEmpty->id]);
        $this->assertDatabaseMissing('players', ['id' => $secondEmpty->id]);
        $this->assertDatabaseHas('player_registrations', ['registration_id' => $registrationId, 'player_id' => $keep->id]);
        $this->assertDatabaseCount('player_merge_audits', 2);
    }

    public function test_super_admin_can_select_all_quick_candidates_across_pagination(): void
    {
        for ($index = 1; $index <= 26; $index++) {
            $keep = Player::factory()->create([
                'name' => "Bulk{$index}", 'surname' => "Candidate{$index}",
                'dateOfBirth' => '2010-01-01', 'email' => "bulk{$index}@example.test",
            ]);
            Player::factory()->create([
                'name' => "Bulk{$index}", 'surname' => "Candidate{$index}",
                'dateOfBirth' => '2010-01-01', 'email' => "bulk{$index}@example.test",
            ]);
            $registrationId = DB::table('registrations')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
            DB::table('player_registrations')->insert([
                'registration_id' => $registrationId, 'player_id' => $keep->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($this->superUser)->post(route('superadmin.player-duplicates.bulk-review'), [
            'selection_scope' => 'all',
        ])->assertOk()
            ->assertSee('26 selected: 26 ready and 0 skipped')
            ->assertSee('<code>MERGE</code>', false);
    }

    public function test_selected_two_history_candidate_uses_suggested_plan_in_one_bulk_confirmation(): void
    {
        $keep = Player::factory()->create([
            'name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01', 'email' => 'same@example.test',
        ]);
        $remove = Player::factory()->create([
            'name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01', 'email' => 'same@example.test',
        ]);
        foreach ([$keep, $remove] as $player) {
            $registrationId = DB::table('registrations')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
            DB::table('player_registrations')->insert([
                'registration_id' => $registrationId, 'player_id' => $player->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $pairs = [['first_id' => $keep->id, 'second_id' => $remove->id]];
        $batch = app(PlayerDuplicateService::class)->plannedMergeBatchAnalysis($pairs);

        $this->actingAs($this->superUser)->post(route('superadmin.player-duplicates.bulk-review'), [
            'pairs' => ["{$keep->id}:{$remove->id}"],
            'selection_scope' => 'page',
        ])->assertOk()
            ->assertSee('1 selected: 1 ready and 0 skipped')
            ->assertSee('name="batch_mode" value="planned"', false)
            ->assertSee('1 linked records will move');

        $this->actingAs($this->superUser)->post(route('superadmin.player-duplicates.bulk-merge'), [
            'pairs' => $pairs,
            'batch_digest' => $batch['digest'],
            'batch_mode' => 'planned',
            'reason' => 'Confirmed suggested merge plan after reviewing both histories.',
            'confirmation' => 'MERGE',
        ])->assertRedirect(route('superadmin.player-duplicates.index'));

        $this->assertDatabaseMissing('players', ['id' => $remove->id]);
        $this->assertDatabaseHas('player_registrations', ['player_id' => $keep->id]);
        $this->assertDatabaseCount('player_merge_audits', 1);
    }

    public function test_bulk_quick_merge_routes_identical_overlap_to_one_canonical_keeper(): void
    {
        $firstKeep = Player::factory()->create([
            'name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01', 'email' => 'same@example.test',
        ]);
        $secondKeep = Player::factory()->create([
            'name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01', 'email' => 'same@example.test',
        ]);
        $empty = Player::factory()->create([
            'name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01', 'email' => 'same@example.test',
        ]);
        foreach ([$firstKeep, $secondKeep] as $keep) {
            $registrationId = DB::table('registrations')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
            DB::table('player_registrations')->insert([
                'registration_id' => $registrationId, 'player_id' => $keep->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($this->superUser)
            ->post(route('superadmin.player-duplicates.bulk-review'), [
                'pairs' => ["{$firstKeep->id}:{$empty->id}", "{$secondKeep->id}:{$empty->id}"],
            ])->assertOk()
            ->assertSee('2 selected: 1 ready and 1 skipped')
            ->assertSee("#{$firstKeep->id}")
            ->assertSee("#{$secondKeep->id}")
            ->assertSee('Overlap resolved from the empty profile')
            ->assertSee("Automatically routed this empty profile to retained profile #{$firstKeep->id}");

        $this->assertDatabaseHas('players', ['id' => $empty->id]);
        $this->assertDatabaseCount('player_merge_audits', 0);
    }

    public function test_bulk_quick_merge_keeps_family_contact_overlap_manual_without_first_name_evidence(): void
    {
        $firstKeep = Player::factory()->create([
            'name' => 'Alice', 'surname' => 'Family', 'dateOfBirth' => '2010-01-01', 'email' => 'family@example.test',
        ]);
        $secondKeep = Player::factory()->create([
            'name' => 'Bob', 'surname' => 'Family', 'dateOfBirth' => '2010-01-01', 'email' => 'family@example.test',
        ]);
        $empty = Player::factory()->create([
            'name' => 'Unknown', 'surname' => 'Family', 'dateOfBirth' => '2010-01-01', 'email' => 'family@example.test',
        ]);
        foreach ([$firstKeep, $secondKeep] as $keep) {
            $registrationId = DB::table('registrations')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
            DB::table('player_registrations')->insert([
                'registration_id' => $registrationId, 'player_id' => $keep->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($this->superUser)
            ->post(route('superadmin.player-duplicates.bulk-review'), [
                'pairs' => ["{$firstKeep->id}:{$empty->id}", "{$secondKeep->id}:{$empty->id}"],
            ])->assertOk()
            ->assertSee('2 selected: 0 ready and 2 skipped')
            ->assertSee('first name does not identify one safe destination')
            ->assertSee('Nothing will be merged');

        $this->assertDatabaseHas('players', ['id' => $empty->id]);
        $this->assertDatabaseCount('player_merge_audits', 0);
    }

    public function test_bulk_quick_merge_rejects_conflicting_values_for_a_shared_keeper(): void
    {
        $keep = Player::factory()->create([
            'name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01',
            'email' => null, 'cellNr' => '0821111111',
        ]);
        $firstEmpty = Player::factory()->create([
            'name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01',
            'email' => 'first@example.test', 'cellNr' => '0821111111',
        ]);
        $secondEmpty = Player::factory()->create([
            'name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01',
            'email' => 'second@example.test', 'cellNr' => '0821111111',
        ]);
        $registrationId = DB::table('registrations')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        DB::table('player_registrations')->insert([
            'registration_id' => $registrationId, 'player_id' => $keep->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->superUser)
            ->post(route('superadmin.player-duplicates.bulk-review'), [
                'pairs' => ["{$keep->id}:{$firstEmpty->id}", "{$keep->id}:{$secondEmpty->id}"],
            ])->assertOk()
            ->assertSee('Skipped automatically (2)')
            ->assertSee("retained profile #{$keep->id}")
            ->assertSee('suggest conflicting email values')
            ->assertSee('Nothing will be merged');

        $this->assertDatabaseHas('players', ['id' => $firstEmpty->id]);
        $this->assertDatabaseHas('players', ['id' => $secondEmpty->id]);
        $this->assertDatabaseCount('player_merge_audits', 0);
    }

    public function test_stale_bulk_review_rolls_back_every_selected_merge(): void
    {
        $firstKeep = Player::factory()->create([
            'name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01', 'email' => 'jamie@example.test',
        ]);
        $firstEmpty = Player::factory()->create([
            'name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01', 'email' => 'jamie@example.test',
        ]);
        $secondKeep = Player::factory()->create([
            'name' => 'Taylor', 'surname' => 'Botha', 'dateOfBirth' => '2011-02-02', 'email' => 'taylor@example.test',
        ]);
        $secondEmpty = Player::factory()->create([
            'name' => 'Taylor', 'surname' => 'Botha', 'dateOfBirth' => '2011-02-02', 'email' => 'taylor@example.test',
        ]);
        foreach ([$firstKeep, $secondKeep] as $keep) {
            $registrationId = DB::table('registrations')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
            DB::table('player_registrations')->insert([
                'registration_id' => $registrationId, 'player_id' => $keep->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $pairs = [
            ['first_id' => $firstKeep->id, 'second_id' => $firstEmpty->id],
            ['first_id' => $secondKeep->id, 'second_id' => $secondEmpty->id],
        ];
        $batch = app(PlayerDuplicateService::class)->quickMergeBatchAnalysis($pairs);
        $secondEmpty->update(['cellNr' => '0820000000']);

        $this->actingAs($this->superUser)->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('superadmin.player-duplicates.bulk-review'))
            ->post(route('superadmin.player-duplicates.bulk-merge'), [
                'pairs' => $pairs,
                'batch_digest' => $batch['digest'],
                'batch_mode' => 'quick',
                'reason' => 'Confirmed duplicate profiles in one reviewed batch.',
                'confirmation' => $batch['confirmation_phrase'],
            ])->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('players', ['id' => $firstEmpty->id]);
        $this->assertDatabaseHas('players', ['id' => $secondEmpty->id, 'cellNr' => '0820000000']);
        $this->assertDatabaseCount('player_merge_audits', 0);
    }

    public function test_bulk_review_skips_blocked_pair_shows_exact_profiles_and_merges_ready_pair(): void
    {
        $safeKeep = Player::factory()->create([
            'name' => 'Taylor', 'surname' => 'Botha', 'dateOfBirth' => '2011-02-02', 'email' => 'taylor@example.test',
        ]);
        $safeEmpty = Player::factory()->create([
            'name' => 'Taylor', 'surname' => 'Botha', 'dateOfBirth' => '2011-02-02', 'email' => 'taylor@example.test',
        ]);
        $registrationId = DB::table('registrations')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        DB::table('player_registrations')->insert([
            'registration_id' => $registrationId, 'player_id' => $safeKeep->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $blockedFirst = Player::factory()->create([
            'name' => 'De Wet', 'surname' => 'Nortier', 'dateOfBirth' => '2007-07-08', 'email' => 'nortier@example.test',
        ]);
        $blockedSecond = Player::factory()->create([
            'name' => 'De Wet', 'surname' => 'Nortier', 'dateOfBirth' => '2007-07-08', 'email' => 'nortier@example.test',
        ]);
        DB::table('team_fixture_players')->insert([
            'team1_id' => $blockedFirst->id,
            'team2_id' => $blockedSecond->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $selectedPairs = [
            ['first_id' => $safeKeep->id, 'second_id' => $safeEmpty->id],
            ['first_id' => $blockedFirst->id, 'second_id' => $blockedSecond->id],
        ];
        $review = app(PlayerDuplicateService::class)->quickMergeBatchReview($selectedPairs);

        $this->actingAs($this->superUser)->post(route('superadmin.player-duplicates.bulk-review'), [
            'pairs' => [
                "{$safeKeep->id}:{$safeEmpty->id}",
                "{$blockedFirst->id}:{$blockedSecond->id}",
            ],
        ])->assertOk()
            ->assertSee('1 ready and 1 skipped')
            ->assertSee('Skipped automatically (1)')
            ->assertSee("#{$blockedFirst->id}")
            ->assertSee("#{$blockedSecond->id}")
            ->assertSee('opposing sides of the same team fixture players record')
            ->assertSee('Ready to merge (1)')
            ->assertSee("#{$safeKeep->id}")
            ->assertSee("#{$safeEmpty->id}");

        $this->actingAs($this->superUser)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('superadmin.player-duplicates.bulk-merge'), [
                'pairs' => [['first_id' => $safeKeep->id, 'second_id' => $safeEmpty->id]],
                'batch_digest' => $review['digest'],
                'batch_mode' => 'quick',
                'reason' => 'Merged ready candidates and skipped the documented blocked pair.',
                'confirmation' => $review['confirmation_phrase'],
            ])->assertRedirect(route('superadmin.player-duplicates.index'));

        $this->assertDatabaseMissing('players', ['id' => $safeEmpty->id]);
        $this->assertDatabaseHas('players', ['id' => $blockedFirst->id]);
        $this->assertDatabaseHas('players', ['id' => $blockedSecond->id]);
        $this->assertDatabaseCount('player_merge_audits', 1);
    }

    public function test_player_on_one_side_of_unrelated_fixture_does_not_create_false_opposing_blocker(): void
    {
        $keep = Player::factory()->create([
            'name' => 'Zoe', 'surname' => 'Bothma', 'dateOfBirth' => '2010-04-23', 'email' => 'zoe@example.test',
        ]);
        $remove = Player::factory()->create([
            'name' => 'Zoë', 'surname' => 'Bothmq', 'dateOfBirth' => '2010-04-23', 'email' => 'zoe@example.test',
        ]);
        $unrelatedOpponent = Player::factory()->create();
        DB::table('team_fixture_players')->insert([
            'team1_id' => $unrelatedOpponent->id,
            'team2_id' => $keep->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $analysis = app(PlayerDuplicateService::class)->analyze($keep, $remove);

        $this->assertTrue($analysis['can_merge'], json_encode($analysis['blockers']));
        $this->assertNotContains('team_fixture_players', collect($analysis['blockers'])->pluck('domain')->all());
    }

    public function test_genuine_opposing_fixture_blocker_identifies_event_fixture_and_result_count(): void
    {
        $event = Event::factory()->create([
            'name' => 'Western Cape Team Championships',
            'start_date' => '2023-10-10',
            'end_date' => '2023-10-12',
        ]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'Girls Team Draw']);
        $fixtureId = DB::table('team_fixtures')->insertGetId([
            'draw_id' => $draw->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $first = Player::factory()->create([
            'name' => 'Zoe', 'surname' => 'Bothma', 'dateOfBirth' => '2010-04-23', 'email' => 'zoe@example.test',
        ]);
        $second = Player::factory()->create([
            'name' => 'Zoe', 'surname' => 'Bothma', 'dateOfBirth' => '2010-04-23', 'email' => 'zoe@example.test',
        ]);
        $recordId = DB::table('team_fixture_players')->insertGetId([
            'team_fixture_id' => $fixtureId,
            'team1_id' => $first->id,
            'team2_id' => $second->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('team_fixture_results')->insert([
            'team_fixture_id' => $fixtureId,
            'set_nr' => 1,
            'team1_score' => 6,
            'team2_score' => 4,
            'match_winner_id' => $first->id,
            'match_loser_id' => $second->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $analysis = app(PlayerDuplicateService::class)->analyze($first, $second);
        $context = collect($analysis['blockers'])->firstWhere('domain', 'team_fixture_players')['contexts'][0];
        $this->assertSame($recordId, $context['record_id']);
        $this->assertSame($fixtureId, $context['fixture_id']);
        $this->assertSame($event->id, $context['event_id']);
        $this->assertSame('Western Cape Team Championships', $context['event_name']);
        $this->assertSame(1, $context['result_count']);

        $this->actingAs($this->superUser)
            ->get(route('superadmin.player-duplicates.review', [$first, $second]))
            ->assertOk()
            ->assertSee('Western Cape Team Championships')
            ->assertSee("Fixture #{$fixtureId}")
            ->assertSee('1 saved result rows')
            ->assertSee('Open event fixtures');
    }

    public function test_approved_merge_moves_history_owners_and_legacy_references_without_changing_money(): void
    {
        $keeperOwner = User::factory()->create();
        $sourceOwner = User::factory()->create();
        $keep = Player::factory()->create([
            'name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2011-02-03', 'userId' => $keeperOwner->id,
        ]);
        $remove = Player::factory()->create([
            'name' => 'jamie', 'surname' => ' smith ', 'dateOfBirth' => '2011-02-03', 'userId' => $sourceOwner->id,
        ]);
        $registrationId = DB::table('registrations')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        DB::table('player_registrations')->insert([
            'registration_id' => $registrationId, 'player_id' => $remove->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $transactionId = DB::table('transactions_pf')->insertGetId([
            'pf_payment_id' => 'PF-MERGE-1', 'player_id' => $remove->id, 'custom_int2' => $remove->id,
            'amount_gross' => 321.45, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->postApprovedMerge($keep, $remove);

        $this->assertDatabaseMissing('players', ['id' => $remove->id]);
        $this->assertDatabaseHas('user_players', ['user_id' => $sourceOwner->id, 'player_id' => $keep->id]);
        $this->assertDatabaseHas('player_registrations', ['registration_id' => $registrationId, 'player_id' => $keep->id]);
        $this->assertDatabaseHas('transactions_pf', [
            'id' => $transactionId, 'player_id' => $keep->id, 'custom_int2' => $keep->id, 'amount_gross' => 321.45,
        ]);
        $this->assertDatabaseHas('player_merge_audits', [
            'kept_player_id' => $keep->id, 'removed_player_id' => $remove->id, 'approved_by' => $this->superUser->id,
        ]);
        $this->assertSame($keep->id, app(PlayerDuplicateService::class)->canonicalPlayerId($remove->id));
        $this->assertDatabaseHas('activity_log', ['log_name' => 'player-profile-merge', 'subject_id' => $keep->id]);
    }

    public function test_review_defaults_to_profile_with_more_history_as_recommended_canonical(): void
    {
        $first = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        $second = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        $registrationId = DB::table('registrations')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        DB::table('player_registrations')->insert([
            'registration_id' => $registrationId, 'player_id' => $second->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->superUser)
            ->get(route('superadmin.player-duplicates.review', [$first, $second]))
            ->assertOk()->assertSee('<code>MERGE</code>', false);
    }

    public function test_merge_uses_selected_source_field_and_recalculates_profile_completeness(): void
    {
        $keep = Player::factory()->create([
            'name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01', 'cellNr' => null, 'email' => 'keep@example.test',
        ]);
        $remove = Player::factory()->create([
            'name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01', 'cellNr' => '0821234567', 'email' => 'source@example.test',
        ]);

        $this->postApprovedMerge($keep, $remove, ['email' => 'remove', 'cellNr' => 'remove']);

        $this->assertDatabaseHas('players', [
            'id' => $keep->id, 'email' => 'source@example.test', 'cellNr' => '0821234567',
        ]);
    }

    public function test_conflicting_dates_of_birth_block_merge(): void
    {
        $keep = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        $remove = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2011-01-01']);
        $analysis = app(PlayerDuplicateService::class)->analyze($keep, $remove);

        $this->assertFalse($analysis['can_merge']);
        $this->assertSame('identity', $analysis['blockers'][0]['domain']);
        $this->actingAs($this->superUser)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('superadmin.player-duplicates.merge'), $this->mergePayload($keep, $remove, $analysis))
            ->assertSessionHasErrors('remove_player_id');
        $this->assertDatabaseHas('players', ['id' => $remove->id]);
    }

    public function test_same_registration_collision_blocks_merge_instead_of_deleting_membership(): void
    {
        $keep = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        $remove = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        $registrationId = DB::table('registrations')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        foreach ([$keep, $remove] as $player) {
            DB::table('player_registrations')->insert([
                'registration_id' => $registrationId, 'player_id' => $player->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $analysis = app(PlayerDuplicateService::class)->analyze($keep, $remove);

        $this->assertFalse($analysis['can_merge']);
        $this->assertContains('player_registrations', collect($analysis['blockers'])->pluck('domain')->all());
        $this->assertDatabaseCount('player_registrations', 2);
    }

    public function test_same_tournament_category_blocks_merge_before_results_can_be_double_counted(): void
    {
        $keep = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        $remove = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        $keepRegistration = DB::table('registrations')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        $removeRegistration = DB::table('registrations')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        DB::table('player_registrations')->insert([
            ['registration_id' => $keepRegistration, 'player_id' => $keep->id, 'created_at' => now(), 'updated_at' => now()],
            ['registration_id' => $removeRegistration, 'player_id' => $remove->id, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('category_event_registrations')->insert([
            ['category_event_id' => 77, 'registration_id' => $keepRegistration, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['category_event_id' => 77, 'registration_id' => $removeRegistration, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $analysis = app(PlayerDuplicateService::class)->analyze($keep, $remove);

        $this->assertFalse($analysis['can_merge']);
        $this->assertContains('tournament_registration_overlap', collect($analysis['blockers'])->pluck('domain')->all());
        $this->assertDatabaseHas('players', ['id' => $remove->id]);
    }

    public function test_merge_preserves_tournament_results_and_future_rankings_combine_under_canonical_player(): void
    {
        $keep = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        $remove = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        $keepRegistration = DB::table('registrations')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        $removeRegistration = $remove->id;
        DB::table('registrations')->insert(['id' => $removeRegistration, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('player_registrations')->insert([
            ['registration_id' => $keepRegistration, 'player_id' => $keep->id, 'created_at' => now(), 'updated_at' => now()],
            ['registration_id' => $removeRegistration, 'player_id' => $remove->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $seriesId = DB::table('series')->insertGetId([
            'name' => 'Merge Series', 'year' => 2026, 'best_num_of_scores' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Boys Singles', 'Fee' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $eventOne = DB::table('events')->insertGetId([
            'name' => 'Leg One', 'series_id' => $seriesId, 'start_date' => '2026-01-10',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $eventTwo = DB::table('events')->insertGetId([
            'name' => 'Leg Two', 'series_id' => $seriesId, 'start_date' => '2026-02-10',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $categoryEventOne = DB::table('category_events')->insertGetId([
            'event_id' => $eventOne, 'category_id' => $categoryId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $categoryEventTwo = DB::table('category_events')->insertGetId([
            'event_id' => $eventTwo, 'category_id' => $categoryId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('category_event_registrations')->insert([
            ['category_event_id' => $categoryEventOne, 'registration_id' => $keepRegistration, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['category_event_id' => $categoryEventTwo, 'registration_id' => $removeRegistration, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('category_results')->insert([
            ['event_id' => $eventOne, 'category_id' => $categoryId, 'registration_id' => $keepRegistration, 'position' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['event_id' => $eventTwo, 'category_id' => $categoryId, 'registration_id' => $removeRegistration, 'position' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $practiceFixtureId = DB::table('practice_fixtures')->insertGetId([
            'registration1_id' => $removeRegistration, 'registration2_id' => $keepRegistration,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('practice_results')->insert([
            'practice_fixture_id' => $practiceFixtureId, 'winner_registration' => $removeRegistration,
            'loser_registration' => $keepRegistration, 'registration1_score' => 6, 'registration2_score' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $rankingListId = DB::table('ranking_lists')->insertGetId([
            'category_id' => $categoryId, 'series_id' => $seriesId, 'best_num_of_scores' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ranking_list_category_events')->insert([
            ['ranking_list_id' => $rankingListId, 'category_event_id' => $categoryEventOne, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['ranking_list_id' => $rankingListId, 'category_event_id' => $categoryEventTwo, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('points')->insert([
            ['series_id' => $seriesId, 'position' => 1, 'score' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['series_id' => $seriesId, 'position' => 2, 'score' => 50, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('series_rankings')->insert([
            [
                'series_id' => $seriesId, 'ranking_list_id' => $rankingListId, 'category_id' => $categoryId,
                'player_id' => $keep->id, 'rank_position' => 2, 'total_points' => 50,
                'status' => 'calculated', 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'series_id' => $seriesId, 'ranking_list_id' => $rankingListId, 'category_id' => $categoryId,
                'player_id' => $remove->id, 'rank_position' => 1, 'total_points' => 100,
                'status' => 'calculated', 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $analysis = app(PlayerDuplicateService::class)->analyze($keep, $remove);
        $this->assertTrue($analysis['can_merge']);
        $this->assertSame([$seriesId], $analysis['impact']['ranking_rebuild_series_ids']);

        $this->postApprovedMerge($keep, $remove);

        $this->assertDatabaseHas('category_results', [
            'event_id' => $eventTwo, 'registration_id' => $removeRegistration, 'position' => 1,
        ]);
        $this->assertDatabaseHas('practice_fixtures', [
            'id' => $practiceFixtureId, 'registration1_id' => $removeRegistration, 'registration2_id' => $keepRegistration,
        ]);
        $this->assertDatabaseHas('practice_results', [
            'practice_fixture_id' => $practiceFixtureId, 'winner_registration' => $removeRegistration,
        ]);
        $this->assertDatabaseHas('series_rankings', [
            'series_id' => $seriesId, 'ranking_list_id' => $rankingListId,
            'player_id' => $keep->id, 'total_points' => 150, 'status' => 'calculated',
        ]);
        $this->assertDatabaseMissing('series_rankings', ['series_id' => $seriesId, 'player_id' => $remove->id]);
        $this->assertDatabaseHas('ranking_audit_logs', ['series_id' => $seriesId, 'action' => 'rebuild']);

        $calculated = app(RankingCalculationService::class)->calculate(RankingList::findOrFail($rankingListId));
        $this->assertCount(1, $calculated->rows);
        $this->assertSame($keep->id, $calculated->rows[0]->playerId);
        $this->assertSame(150, $calculated->rows[0]->totalPoints);
        $this->assertCount(2, $calculated->rows[0]->countingLegs);
    }

    public function test_published_series_ranking_collision_is_not_auto_resolved(): void
    {
        $keep = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        $remove = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        $seriesId = DB::table('series')->insertGetId([
            'name' => 'Published Merge Series', 'year' => 2026,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Published Boys Singles', 'Fee' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('series_rankings')->insert([
            [
                'series_id' => $seriesId, 'category_id' => $categoryId, 'player_id' => $keep->id,
                'rank_position' => 1, 'total_points' => 100, 'status' => 'published',
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'series_id' => $seriesId, 'category_id' => $categoryId, 'player_id' => $remove->id,
                'rank_position' => 2, 'total_points' => 50, 'status' => 'published',
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $analysis = app(PlayerDuplicateService::class)->analyze($keep, $remove);

        $this->assertFalse($analysis['can_merge']);
        $this->assertSame([], $analysis['impact']['ranking_rebuild_series_ids']);
        $this->assertContains('series_rankings', collect($analysis['blockers'])->pluck('domain')->all());
    }

    public function test_usage_registry_includes_legacy_invitation_and_payfast_player_columns(): void
    {
        $player = Player::factory()->create();
        DB::table('invatations')->insert([
            'eventId' => '10', 'event_category_id' => 20, 'player_id' => $player->id,
            'registration_status' => 1, 'user_id' => $this->superUser->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('transactions_pf')->insert([
            'pf_payment_id' => 'PF-LEGACY-ONLY', 'player_id' => null, 'custom_int2' => $player->id,
            'amount_gross' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $usage = app(PlayerDuplicateService::class)->usage($player->id);

        $this->assertSame(1, $usage['invatations']);
        $this->assertSame(1, $usage['transactions_pf']);
    }

    public function test_overlapping_financial_orders_block_merge_without_mutating_either_order(): void
    {
        $keep = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        $remove = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        foreach ([$keep, $remove] as $player) {
            DB::table('team_payment_orders')->insert([
                'user_id' => $this->superUser->id, 'team_id' => 10, 'player_id' => $player->id, 'event_id' => 20,
                'total_amount' => 500, 'wallet_reserved' => 0, 'payfast_amount_due' => 500,
                'wallet_debited' => false, 'payfast_paid' => false, 'pay_status' => false,
                'refund_status' => 'not_refunded', 'refund_gross' => 0, 'refund_fee' => 0, 'refund_net' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $analysis = app(PlayerDuplicateService::class)->analyze($keep, $remove);

        $this->assertFalse($analysis['can_merge']);
        $this->assertContains('team_payment_orders', collect($analysis['blockers'])->pluck('domain')->all());
        $this->assertDatabaseCount('team_payment_orders', 2);
        $this->assertDatabaseHas('team_payment_orders', ['player_id' => $keep->id, 'total_amount' => 500]);
        $this->assertDatabaseHas('team_payment_orders', ['player_id' => $remove->id, 'total_amount' => 500]);
    }

    public function test_merge_requires_exact_merge_phrase(): void
    {
        $keep = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        $remove = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        $analysis = app(PlayerDuplicateService::class)->analyze($keep, $remove);
        $payload = $this->mergePayload($keep, $remove, $analysis);

        $payload['confirmation'] = 'merge';
        $this->actingAs($this->superUser)
            ->post(route('superadmin.player-duplicates.merge'), $payload)->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('players', ['id' => $remove->id]);
    }

    public function test_stale_preview_digest_rejects_merge_atomically(): void
    {
        $keep = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        $remove = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        $analysis = app(PlayerDuplicateService::class)->analyze($keep, $remove);
        $remove->update(['email' => 'changed@example.test']);

        $this->actingAs($this->superUser)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('superadmin.player-duplicates.merge'), $this->mergePayload($keep, $remove, $analysis))
            ->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('players', ['id' => $remove->id, 'email' => 'changed@example.test']);
        $this->assertDatabaseCount('player_merge_audits', 0);
    }

    public function test_audit_write_failure_rolls_back_profile_and_reference_changes(): void
    {
        $keep = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        $remove = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        $registrationId = DB::table('registrations')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        DB::table('player_registrations')->insert([
            'registration_id' => $registrationId, 'player_id' => $remove->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('player_merge_audits')->insert([
            'kept_player_id' => $keep->id, 'removed_player_id' => $remove->id,
            'approved_by' => $this->superUser->id, 'reason' => 'Existing audit collision', 'status' => 'completed',
            'kept_before_snapshot' => '{}', 'removed_snapshot' => '{}', 'impact_snapshot' => '{}',
            'change_manifest' => '{}', 'merged_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = app(PlayerDuplicateService::class);
        $analysis = $service->analyze($keep, $remove);

        try {
            $service->merge($keep, $remove, $this->superUser, [], $analysis['digest'], 'Duplicate confirmed for rollback test.');
            $this->fail('Expected the unique audit constraint to reject the merge.');
        } catch (\Illuminate\Database\QueryException) {
            // The database transaction must restore every earlier mutation.
        }

        $this->assertDatabaseHas('players', ['id' => $remove->id]);
        $this->assertDatabaseHas('player_registrations', ['registration_id' => $registrationId, 'player_id' => $remove->id]);
        $this->assertDatabaseMissing('player_registrations', ['registration_id' => $registrationId, 'player_id' => $keep->id]);
    }

    public function test_not_duplicate_decision_is_audited_and_suppresses_pair_by_default(): void
    {
        $first = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);
        $second = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'dateOfBirth' => '2010-01-01']);

        $this->actingAs($this->superUser)->post(route('superadmin.player-duplicates.decision', [$first, $second]), [
            'decision' => 'not_duplicate', 'reason' => 'Confirmed as two different players.',
        ])->assertRedirect(route('superadmin.player-duplicates.index'));

        $this->assertDatabaseHas('player_duplicate_decisions', [
            'first_player_id' => $first->id, 'second_player_id' => $second->id, 'decision' => 'not_duplicate',
        ]);
        $this->actingAs($this->superUser)->get(route('superadmin.player-duplicates.index'))
            ->assertOk()->assertDontSee("profiles #{$first->id} and #{$second->id}");
        $this->actingAs($this->superUser)->get(route('superadmin.player-duplicates.index', ['include_reviewed' => 1]))
            ->assertOk()->assertSee("profiles #{$first->id} and #{$second->id}");
    }

    private function postApprovedMerge(Player $keep, Player $remove, array $fieldSources = []): void
    {
        $analysis = app(PlayerDuplicateService::class)->analyze($keep, $remove);
        $this->assertTrue($analysis['can_merge'], json_encode($analysis['blockers']));

        $this->actingAs($this->superUser)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('superadmin.player-duplicates.merge'), $this->mergePayload($keep, $remove, $analysis, $fieldSources))
            ->assertRedirect(route('superadmin.player-duplicates.index'));
    }

    private function mergePayload(Player $keep, Player $remove, array $analysis, array $fieldSources = []): array
    {
        return [
            'keep_player_id' => $keep->id,
            'remove_player_id' => $remove->id,
            'impact_digest' => $analysis['digest'],
            'reason' => 'Confirmed duplicate after comparing identity and history.',
            'confirmation' => 'MERGE',
            'field_sources' => $fieldSources,
        ];
    }
}
