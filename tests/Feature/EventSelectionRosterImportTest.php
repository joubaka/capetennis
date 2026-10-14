<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, Event, EventAdmin, EventRegion, EventSelectionFinalization, EventSelectionReopening, EventSelectionRosterImport, EventType, NoProfileTeamPlayer, Player, Team, TeamPlayer, TeamRegion, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail, Queue};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventSelectionRosterImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_distinct_source_categories_with_same_group_import_and_reuse_unique_matching_destination_category(): void
    {
        [$source, $target, $final, $region, $actor, $players, $sourceTeam] = $this->scenario();
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $source->id, 'category_id' => Category::factory()->create(['name' => 'U10 Boys'])->id]);
        $otherTeam = $sourceTeam->replicate();
        $otherTeam->category_event_id = $otherCategory->id;
        $otherTeam->save();
        $groups = $final->groups;
        foreach ($groups['10-boys']['snapshot'] as &$row) $row['source_team_ids'][] = $otherTeam->id;
        unset($row);
        DB::table('event_selection_finalizations')->where('id', $final->id)->update(['groups' => json_encode($groups)]);
        $targetCategory = CategoryEvent::factory()->create(['event_id' => $target->id, 'category_id' => Category::factory()->create(['name' => 'u/10 Boys'])->id]);
        $url = route('backend.selection-import.index', ['event' => $target, 'source_event_id' => $source->id, 'import_mode' => 'approved_team']);
        $this->get($url)->assertOk()->assertSee($players[1]->name)->assertSee('Reserve')->assertDontSee('Unable to review these teams.');
        $input = ['finalization_id' => $final->id, 'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1];
        $this->post(route('backend.selection-import.store', $target), $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, CategoryEvent::where('event_id', $target->id)->count());
        $this->assertDatabaseHas('teams', ['category_event_id' => $targetCategory->id, 'region_id' => $region->id, 'published' => 0]);
        $this->assertSame(['main', 'reserve'], array_column(EventSelectionRosterImport::first()->groups['10-boys']['players'], 'status'));
    }

    public function test_matching_existing_destination_team_uses_semantic_group_and_ambiguous_new_category_requires_explicit_team(): void
    {
        [$source, $target, $final, $region, $actor] = $this->scenario();
        $category = CategoryEvent::factory()->create(['event_id' => $target->id, 'category_id' => Category::factory()->create(['name' => 'U10 Boys'])->id]);
        CategoryEvent::factory()->create(['event_id' => $target->id, 'category_id' => Category::factory()->create(['name' => 'u/10 Boys'])->id]);
        $team = new Team(['name' => 'Matching destination', 'category_event_id' => $category->id, 'region_id' => $region->id, 'published' => false, 'num_team_members' => 1, 'year' => 2026]);
        $team->forceFill(['user_id' => $actor->id, 'personal_team' => false])->save();
        $input = ['finalization_id' => $final->id, 'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1];
        $url = route('backend.selection-import.store', $target);
        $this->postJson($url, $input)->assertUnprocessable()->assertJsonValidationErrors('mapping');
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
        $this->assertDatabaseCount('no_profile_team_players', 0);
        $this->get(route('backend.selection-import.index', ['event' => $target, 'source_event_id' => $source->id]))->assertOk()->assertSee('Matching destination');
        $foreignTeam = $team->replicate();
        $foreignTeam->name = 'Foreign semantic match';
        $foreignTeam->category_event_id = CategoryEvent::factory()->create(['event_id' => $source->id, 'category_id' => $category->category_id])->id;
        $foreignTeam->save();
        $this->get(route('backend.selection-import.index', ['event' => $target, 'source_event_id' => $source->id]))->assertOk()->assertDontSee('Foreign semantic match');
        $this->postJson($url, array_replace($input, ['mapping' => ['10-boys' => $foreignTeam->id]]))->assertUnprocessable();
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
        $input['mapping']['10-boys'] = $team->id;
        $this->post($url, $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('event_selection_roster_imports', 1);
        $this->assertDatabaseCount('no_profile_team_players', 2);
        $this->assertDatabaseHas('teams', ['id' => $team->id, 'category_event_id' => $category->id]);
    }

    public function test_every_source_category_is_validated_and_foreign_missing_or_mismatched_categories_make_no_import(): void
    {
        [$source, $target, $final, $region, $actor, $players, $team] = $this->scenario();
        $original = $team->category_event_id;
        $foreign = CategoryEvent::factory()->create(['event_id' => $target->id, 'category_id' => $team->category->category_id]);
        $url = route('backend.selection-import.store', $target);
        $input = ['finalization_id' => $final->id, 'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1];
        $team->update(['category_event_id' => $foreign->id]);
        $this->postJson($url, $input)->assertUnprocessable();
        $team->update(['category_event_id' => null]);
        $this->postJson($url, $input)->assertUnprocessable();
        $team->update(['category_event_id' => $original]);
        $mismatch = CategoryEvent::factory()->create(['event_id' => $source->id, 'category_id' => Category::factory()->create(['name' => 'u/12 Girls'])->id]);
        $otherTeam = $team->replicate(); $otherTeam->category_event_id = $mismatch->id; $otherTeam->save();
        $groups = $final->groups;
        $groups['10-boys']['snapshot'][0]['source_team_ids'][] = $otherTeam->id;
        DB::table('event_selection_finalizations')->where('id', $final->id)->update(['groups' => json_encode($groups)]);
        $this->postJson($url, $input)->assertUnprocessable();
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
        $this->assertDatabaseCount('no_profile_team_players', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_ajax_source_choices_are_private_escaped_and_do_not_run_full_import_preview(): void
    {
        [$source, $target, $final, $region, $actor, $players, $team] = $this->scenario();
        $team->category->category->update(['name' => 'u/12 Girls']);
        $source->update(['name' => '<script>alert(1)</script> Source weekend']);
        $query = ['event' => $target, 'source_event_id' => $source->id, 'source_choices' => 1];
        $response = $this->get(route('backend.selection-import.index', $query))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Selection-Source-Choices', '1')
            ->assertSee('Approved selected team')->assertSee('Source regions')->assertSee($source->name)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<html', false)
            ->assertDontSee('name="mapping[', false)->assertDontSee('Unable to review these teams.');
        if (getenv('CT_SELECTION_IMPORT_BROWSER_FIXTURE')) {
            file_put_contents(storage_path('app/testing/selection-import-ajax-choices.html'), $response->getContent());
            file_put_contents(storage_path('app/testing/selection-import-ajax-page.html'), $this->get(route('backend.selection-import.index', $target))->getContent());
        }
        EventSelectionReopening::create(['finalization_id' => $final->id, 'reopened_by' => $actor->id, 'reason' => 'Review', 'reopened_at' => now()]);
        $this->get(route('backend.selection-import.index', $query))->assertOk()->assertSee('No teams are approved yet.')
            ->assertDontSee('Review approved selected team');
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
    }

    public function test_ajax_source_choices_require_both_event_permissions_and_reject_mixed_preview_inputs(): void
    {
        [$source, $target] = $this->scenario();
        $query = ['event' => $target, 'source_event_id' => $source->id, 'source_choices' => 1];
        $this->getJson(route('backend.selection-import.index', $query + ['import_mode' => 'approved_team']))->assertUnprocessable();
        $this->getJson(route('backend.selection-import.index', ['event' => $target, 'source_choices' => 1]))->assertUnprocessable();
        $this->getJson(route('backend.selection-import.index', ['event' => $target, 'source_choices' => 'yes']))->assertUnprocessable();
        $outsider = User::factory()->create()->assignRole('admin');
        $this->actingAs($outsider)->get(route('backend.selection-import.index', $query))->assertForbidden();
        $outsider->adminEvents()->attach($target);
        $this->get(route('backend.selection-import.index', $query))->assertForbidden();
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
    }

    public function test_approved_selected_team_reviews_and_imports_all_groups_and_reserves_idempotently(): void
    {
        [$source, $target, $final, $region, $actor, $players] = $this->scenario();
        [$secondRegion, $secondPlayer] = $this->addSecondRegionGroup($source, $final, $actor);
        $this->get(route('backend.selection-import.index', ['event' => $target, 'source_event_id' => $source->id, 'choose_regions' => 1]))
            ->assertOk()->assertSee('Approved selected team · '.$source->name)->assertSee('Review approved selected team');
        $this->get(route('backend.selection-import.index', ['event' => $target, 'source_event_id' => $source->id, 'import_mode' => 'approved_team']))
            ->assertOk()->assertSee($players[0]->name)->assertSee($players[1]->name)->assertSee($secondPlayer->name)
            ->assertSee('Reserve')->assertSee('data-import-mode="approved_team"', false)
            ->assertDontSee('type="hidden" name="source_region_ids[]"', false);
        $input = ['import_mode' => 'approved_team', 'source_event_id' => $source->id, 'finalization_id' => $final->id,
            'region_id' => $region->id, 'mapping' => ['10-boys' => 0, '12-girls' => 0], 'confirm_import' => 1];
        $url = route('backend.selection-import.store', $target);
        $this->post($url, $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('event_selection_roster_imports', 1);
        $this->assertDatabaseCount('no_profile_team_players', 3);
        $this->assertSame([(string) $players[1]->id, (string) $players[0]->id], array_column(EventSelectionRosterImport::first()->groups['10-boys']['players'], 'key'));
        $this->assertSame(['main', 'reserve'], array_column(EventSelectionRosterImport::first()->groups['10-boys']['players'], 'status'));
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('team_payment_orders', 0);
    }

    public function test_approved_team_modes_reject_mixed_or_missing_selection_and_wrong_approval(): void
    {
        [$source, $target, $final, $region] = $this->scenario();
        $url = route('backend.selection-import.store', $target);
        $input = ['import_mode' => 'approved_team', 'source_event_id' => $source->id, 'finalization_id' => $final->id,
            'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1];
        $this->postJson($url, $input + ['source_region_ids' => [$region->id]])->assertUnprocessable();
        $missingSource = $input;
        unset($missingSource['source_event_id']);
        $this->postJson($url, $missingSource)->assertUnprocessable();
        $this->postJson($url, array_replace($input, ['import_mode' => 'regions']))->assertUnprocessable();
        $this->postJson($url, array_replace($input, ['source_event_id' => $target->id]))->assertUnprocessable();
        $this->getJson(route('backend.selection-import.index', ['event' => $target, 'source_event_id' => $source->id,
            'import_mode' => 'approved_team', 'review_regions' => 1]))->assertUnprocessable();
        EventSelectionReopening::create(['finalization_id' => $final->id, 'reopened_by' => $final->finalized_by, 'reason' => 'Review', 'reopened_at' => now()]);
        $this->get(route('backend.selection-import.index', ['event' => $target, 'source_event_id' => $source->id, 'import_mode' => 'approved_team']))
            ->assertOk()->assertSee('No teams are approved yet.')->assertDontSee('Review approved selected team')->assertDontSee('name="mapping[', false);
        $this->postJson($url, $input)->assertConflict();
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
        $this->assertDatabaseCount('no_profile_team_players', 0);
    }

    public function test_source_without_any_approved_selection_cannot_review_an_approved_team(): void
    {
        [$source, $target, $final] = $this->scenario();
        DB::table('event_selection_finalizations')->where('id', $final->id)->delete();
        $this->get(route('backend.selection-import.index', ['event' => $target, 'source_event_id' => $source->id, 'import_mode' => 'approved_team']))
            ->assertOk()->assertSee('No teams are approved yet.')->assertDontSee('Review approved selected team')->assertDontSee('name="mapping[', false);
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
    }

    public function test_approved_team_requires_access_to_both_events(): void
    {
        [$source, $target, $final, $region] = $this->scenario();
        $outsider = User::factory()->create()->assignRole('admin');
        $this->actingAs($outsider);
        $query = ['event' => $target, 'source_event_id' => $source->id, 'import_mode' => 'approved_team'];
        $input = ['import_mode' => 'approved_team', 'source_event_id' => $source->id, 'finalization_id' => $final->id,
            'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1];
        $this->get(route('backend.selection-import.index', $query))->assertForbidden();
        $this->postJson(route('backend.selection-import.store', $target), $input)->assertForbidden();
        $outsider->adminEvents()->attach($target);
        $this->get(route('backend.selection-import.index', $query))->assertForbidden();
        $this->postJson(route('backend.selection-import.store', $target), $input)->assertForbidden();
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
    }

    public function test_region_chooser_does_not_validate_players_or_categories_but_review_and_import_do(): void
    {
        [$source, $target, $final, $region, $actor, $players, $team] = $this->scenario();
        $team->category->category->update(['name' => 'u/12 Girls']);
        $query = ['event' => $target, 'source_event_id' => $source->id, 'choose_regions' => 1];
        $this->get(route('backend.selection-import.index', $query))->assertOk()
            ->assertSee('Included in finalized selection')->assertDontSee('Unable to review these teams.');
        $query += ['review_regions' => 1, 'source_region_ids' => [$region->id]];
        $response = $this->get(route('backend.selection-import.index', $query))->assertOk()
            ->assertSee('The source category does not match the finalized age and gender group.')
            ->assertDontSee('name="mapping[', false)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(1, substr_count($response->getContent(), 'The source category does not match the finalized age and gender group.'));
        $this->assertMatchesRegularExpression('/id="source-region-'.$region->id.'"[^>]*checked/', $response->getContent());
        $this->postJson(route('backend.selection-import.store', $target), ['finalization_id' => $final->id,
            'region_id' => $region->id, 'source_region_ids' => [$region->id], 'mapping' => ['10-boys' => 0], 'confirm_import' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
        $this->assertDatabaseCount('no_profile_team_players', 0);
    }

    public function test_region_chooser_rejects_foreign_region_metadata_and_missing_region_review_stays_on_page(): void
    {
        [$source, $target, $final, $region] = $this->scenario();
        $query = ['event' => $target, 'source_event_id' => $source->id, 'choose_regions' => 1, 'review_regions' => 1];
        $this->get(route('backend.selection-import.index', $query))->assertOk()->assertSee('Select at least one finalized region to review.');
        $groups = $final->groups;
        $foreign = TeamRegion::create(['region_name' => 'Foreign region']);
        $groups['10-boys']['region_ids'] = [$foreign->id];
        DB::table('event_selection_finalizations')->where('id', $final->id)->update(['groups' => json_encode($groups)]);
        $this->get(route('backend.selection-import.index', $query))->assertOk()
            ->assertSee('The finalized selection has regions outside the source event.')
            ->assertDontSee('Included in finalized selection')->assertDontSee('name="mapping[', false);
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
    }

    public function test_selected_team_and_reserves_import_in_frozen_order_privately_unpaid_and_retry_does_not_duplicate(): void
    {
        [$source, $target, $final, $region, $actor, $players] = $this->scenario();
        Queue::fake(); Mail::fake();
        $url = route('backend.selection-import.index', ['event' => $target, 'source_event_id' => $source->id]);
        $response = $this->get($url)->assertOk()->assertSee('Reserve')->assertSee($players[1]->name)->assertDontSee($players[2]->name)->assertDontSee('Private selection note');
        if (getenv('CT_SELECTION_IMPORT_BROWSER_FIXTURE')) file_put_contents(storage_path('app/testing/selection-import-preview.html'), $response->getContent());
        $input = ['finalization_id' => $final->id, 'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1];
        $this->post(route('backend.selection-import.store', $target), $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('backend.selection-import.store', $target), $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('event_selection_roster_imports', 1);
        $this->assertDatabaseCount('team_payment_orders', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('team_selection_invitations', 0);
        $team = Team::whereHas('category', fn ($q) => $q->where('event_id', $target->id))->firstOrFail();
        $this->assertEquals($region->id, $team->region_id);
        $this->assertFalse((bool) $team->published);
        $this->assertEquals(1, $team->num_team_members); // Reserve is outside the main-team capacity.
        $this->assertSame([$players[1]->id, $players[0]->id], TeamPlayer::where('team_id', $team->id)->pluck('player_id')->all());
        $this->assertSame([0, 0], TeamPlayer::where('team_id', $team->id)->pluck('pay_status')->all());
        $this->assertDatabaseCount('no_profile_team_players', 2);
        $this->assertSame(['main', 'reserve'], array_column(EventSelectionRosterImport::first()->groups['10-boys']['players'], 'status'));
        $this->assertSame($final->groups, $final->fresh()->groups);
        $this->assertFalse($target->fresh()->results_published);
        Queue::assertNothingPushed(); Mail::assertNothingSent();
    }

    public function test_import_requires_manager_access_to_both_events_and_rejects_reopened_source(): void
    {
        [$source, $target, $final, $region, $actor] = $this->scenario();
        $input = ['finalization_id' => $final->id, 'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1];
        $url = route('backend.selection-import.store', $target);
        $outsider = User::factory()->create()->assignRole('admin');
        $this->actingAs($outsider)->postJson($url, $input)->assertForbidden();
        $outsider->adminEvents()->attach($target);
        $this->postJson($url, $input)->assertForbidden();
        $this->get(route('backend.selection-import.index', ['event' => $target, 'source_event_id' => $source->id, 'choose_regions' => 1]))->assertForbidden();
        $this->get(route('backend.selection-import.index', $target))->assertOk()->assertDontSee($source->name);
        $this->actingAs($actor);
        EventSelectionReopening::create(['finalization_id' => $final->id, 'reopened_by' => $actor->id, 'reason' => 'Review', 'reopened_at' => now()]);
        $this->postJson($url, $input)->assertConflict();
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
    }

    public function test_wrong_region_cross_event_team_occupied_team_and_missing_confirmation_make_no_changes(): void
    {
        [$source, $target, $final, $region, $actor, $players, $sourceTeam] = $this->scenario();
        $url = route('backend.selection-import.store', $target);
        $input = ['finalization_id' => $final->id, 'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1];
        $this->postJson($url, array_replace($input, ['confirm_import' => 0]))->assertUnprocessable();
        $foreignRegion = TeamRegion::create(['region_name' => 'Other']);
        $this->postJson($url, array_replace($input, ['region_id' => $foreignRegion->id]))->assertUnprocessable();
        $this->postJson($url, array_replace($input, ['mapping' => ['10-boys' => $sourceTeam->id]]))->assertUnprocessable();
        $category = CategoryEvent::create(['event_id' => $target->id, 'category_id' => $sourceTeam->category->category_id, 'entry_fee' => 0]);
        $occupied = new Team(['name' => 'Occupied', 'region_id' => $region->id, 'category_event_id' => $category->id, 'num_team_members' => 2, 'published' => false, 'year' => 2026]);
        $occupied->forceFill(['user_id' => $actor->id, 'personal_team' => false])->save();
        TeamPlayer::create(['team_id' => $occupied->id, 'player_id' => $players[0]->id, 'rank' => 1, 'pay_status' => 1]);
        $this->postJson($url, array_replace($input, ['mapping' => ['10-boys' => $occupied->id]]))->assertUnprocessable();
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
        $this->assertDatabaseCount('no_profile_team_players', 0);
        $this->assertDatabaseHas('team_players', ['team_id' => $occupied->id, 'player_id' => $players[0]->id, 'pay_status' => 1]);
    }

    public function test_imported_identity_is_resolved_from_actual_participant_not_historical_anchor_and_relink_is_rejected(): void
    {
        [$source, $target, $final, $region, $actor, $players, $sourceTeam] = $this->scenario();
        $person = NoProfileTeamPlayer::create(['team_id' => $sourceTeam->id, 'name' => 'External', 'surname' => 'Selected', 'rank' => 1, 'pay_status' => 1]);
        $groups = $final->groups;
        $key = 'imported:'.$sourceTeam->id.':99999';
        $groups['10-boys']['snapshot'] = [['id' => $key, 'name' => 'External Selected', 'source_team_ids' => [$sourceTeam->id], 'source_participants' => [['team_id' => $sourceTeam->id, 'type' => 'imported', 'id' => $person->id, 'profile_id' => null]]]];
        $groups['10-boys']['selected_keys'] = [$key];
        $groups['10-boys']['selection_statuses'] = [$key => ['position' => 1, 'status' => 'main']];
        DB::table('event_selection_finalizations')->where('id', $final->id)->update(['groups' => json_encode($groups)]);
        $input = ['finalization_id' => $final->id, 'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1];
        $person->update(['player_profile' => $players[0]->id]);
        $this->postJson(route('backend.selection-import.store', $target), $input)->assertUnprocessable();
        $person->update(['player_profile' => null]);
        $this->post(route('backend.selection-import.store', $target), $input)->assertRedirect()->assertSessionHasNoErrors();
        $copy = NoProfileTeamPlayer::where('team_id', '!=', $sourceTeam->id)->firstOrFail();
        $this->assertSame('External', $copy->name); $this->assertEquals(0, $copy->pay_status); $this->assertNull($copy->player_profile); $this->assertNull($copy->claimed_by_user_id);
    }

    public function test_linked_imported_snapshot_without_ratings_preserves_attested_profile_and_legacy_copy_stays_unlinked(): void
    {
        [$source, $target, $final, $region, $actor, $players, $sourceTeam] = $this->scenario();
        $person = NoProfileTeamPlayer::create(['team_id' => $sourceTeam->id, 'name' => 'Linked', 'surname' => 'External', 'player_profile' => $players[0]->id, 'rank' => 1, 'pay_status' => 1]);
        $key = 'imported:'.$sourceTeam->id.':'.$person->id;
        $groups = ['10-boys' => ['snapshot' => [['id' => $key, 'name' => 'Linked External', 'source_team_ids' => [$sourceTeam->id], 'source_participants' => [['team_id' => $sourceTeam->id, 'type' => 'imported', 'id' => $person->id, 'profile_id' => $players[0]->id]]]],
            'selected_keys' => [$key], 'selection_statuses' => [$key => ['position' => 1, 'status' => 'main']]]];
        DB::table('event_selection_finalizations')->where('id', $final->id)->update(['groups' => json_encode($groups)]);
        $preview = app(\App\Services\EventSelectionRosterImportService::class)->preview($target, $final->fresh(), $actor);
        $this->assertEquals($players[0]->id, $preview['groups']['10-boys']['players'][0]['profile_id']);
        unset($groups['10-boys']['snapshot'][0]['source_participants'][0]['profile_id']);
        DB::table('event_selection_finalizations')->where('id', $final->id)->update(['groups' => json_encode($groups)]);
        $preview = app(\App\Services\EventSelectionRosterImportService::class)->preview($target, $final->fresh(), $actor);
        $this->assertNull($preview['groups']['10-boys']['players'][0]['profile_id']);
    }

    public function test_canonical_result_draft_finalize_and_import_preserves_linked_imported_identity_without_ratings(): void
    {
        [$source, $target, $oldFinal, $region, $actor, $players, $sourceTeam] = $this->scenario();
        DB::table('event_selection_finalizations')->where('id', $oldFinal->id)->delete();
        $source->team_result_settings = ['team_size' => 1, 'reserve_count' => 0]; $source->save();
        $sourceTeam->update(['noProfile' => true]);
        $member = NoProfileTeamPlayer::create(['team_id' => $sourceTeam->id, 'name' => 'Canonical', 'surname' => 'Imported', 'rank' => 1, 'player_profile' => $players[0]->id, 'pay_status' => 1]);
        $away = new Team(['name' => 'Away u/10 Boys', 'category_event_id' => $sourceTeam->category_event_id, 'region_id' => $region->id, 'published' => true, 'num_team_members' => 1, 'year' => 2026]);
        $away->forceFill(['user_id' => $actor->id, 'personal_team' => false])->save();
        TeamPlayer::create(['team_id' => $away->id, 'player_id' => $players[1]->id, 'rank' => 1, 'pay_status' => 1]);
        $draw = \App\Models\Draw::factory()->create(['event_id' => $source->id, 'category_event_id' => $sourceTeam->category_event_id]);
        $fixture = \App\Models\TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'numSets' => 3, 'round_nr' => 1, 'match_nr' => 1]);
        $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_no_profile_id' => $member->id, 'team2_id' => $players[1]->id]);
        foreach ([1, 2] as $set) $fixture->teamResults()->create(['set_nr' => $set, 'team1_score' => 6, 'team2_score' => 1]);
        $ranking = app(\App\Services\TeamResultRankingService::class)->ranking($source, '10-boys', [$region->id], ['singles']);
        $key = 'imported:'.$sourceTeam->id.':'.$member->id;
        $row = $ranking->firstWhere('id', $key);
        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('rating_player_id', $row);
        $this->assertSame($players[0]->id, $row['source_participants'][0]['profile_id']);
        app(\App\Services\TeamResultSelectionDraftService::class)->save($source, ['group_key' => '10-boys', 'version' => 0, 'region_ids' => [$region->id], 'formats' => ['singles'], 'selected_keys' => [$key], 'reasons' => []], $actor->id);
        $final = app(\App\Services\EventSelectionFinalizationService::class)->finalize($source->fresh(), $actor, ['10-boys' => 1], 1, 0);
        $this->post(route('backend.selection-import.store', $target), ['finalization_id' => $final->id, 'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $team = Team::whereHas('category', fn ($q) => $q->where('event_id', $target->id))->firstOrFail();
        $this->assertDatabaseHas('team_players', ['team_id' => $team->id, 'player_id' => $players[0]->id, 'rank' => 1, 'pay_status' => 0]);
        $this->assertDatabaseHas('no_profile_team_players', ['team_id' => $team->id, 'player_profile' => $players[0]->id, 'name' => 'Canonical', 'pay_status' => 0]);
    }

    public function test_duplicate_destination_and_target_eligibility_reject_atomically(): void
    {
        [$source, $target, $final, $region, $actor, $players, $sourceTeam] = $this->scenario();
        $url = route('backend.selection-import.store', $target);
        $input = ['finalization_id' => $final->id, 'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1];
        $this->mock(\App\Services\PlayerEligibilityService::class, function ($mock) {
            $mock->shouldReceive('assertEligible')->once()->andThrow(new \RuntimeException('Active event restriction'));
        });
        $this->postJson($url, $input)->assertUnprocessable()->assertJsonValidationErrors('mapping');
        $this->assertSame(0, Team::whereHas('category', fn ($q) => $q->where('event_id', $target->id))->count());
        $this->assertDatabaseCount('no_profile_team_players', 0);
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
        $this->app->forgetInstance(\App\Services\PlayerEligibilityService::class);
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $target->id]);
        $otherTeam = new Team(['name' => 'Other category team', 'category_event_id' => $otherCategory->id, 'region_id' => $region->id, 'published' => false, 'num_team_members' => 1, 'year' => 2026]);
        $otherTeam->forceFill(['user_id' => $actor->id, 'personal_team' => false])->save();
        TeamPlayer::create(['team_id' => $otherTeam->id, 'player_id' => $players[0]->id, 'rank' => 1, 'pay_status' => 0]);
        $this->postJson($url, $input)->assertUnprocessable();
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
        $this->assertDatabaseCount('no_profile_team_players', 0);
        $this->assertSame(1, Team::whereHas('category', fn ($q) => $q->where('event_id', $target->id))->count());
    }

    public function test_source_region_chooser_shows_all_regions_and_unfinalized_source_events(): void
    {
        [$source, $target, $final, $region, $actor] = $this->scenario();
        $unselected = TeamRegion::create(['region_name' => 'Unselected source region']);
        $source->regions()->attach($unselected);
        $url = route('backend.selection-import.index', ['event' => $target, 'source_event_id' => $source->id, 'choose_regions' => 1]);
        $response = $this->get($url)->assertOk()->assertSee('Regions in '.$source->name)->assertSee($region->region_name)
            ->assertSee($unselected->region_name)->assertSee('Included in finalized selection')->assertSee('No finalized selection')
            ->assertDontSee('name="mapping[', false);
        $this->assertMatchesRegularExpression('/id="source-region-'.$unselected->id.'"[^>]*disabled/', $response->getContent());
        if (getenv('CT_SELECTION_IMPORT_BROWSER_FIXTURE')) file_put_contents(storage_path('app/testing/selection-import-regions.html'), $response->getContent());
        EventSelectionReopening::create(['finalization_id' => $final->id, 'reopened_by' => $actor->id, 'reason' => 'Review', 'reopened_at' => now()]);
        $this->get(route('backend.selection-import.index', $target))->assertOk()->assertSee($source->name);
        $this->get($url)->assertOk()->assertDontSee('Included in finalized selection')->assertSee('Finalize the saved teams');
    }

    public function test_region_subset_import_preserves_groups_and_retry_but_rejects_changed_subset(): void
    {
        [$source, $target, $final, $region, $actor, $players, $sourceTeam] = $this->scenario();
        [$secondRegion, $secondPlayer] = $this->addSecondRegionGroup($source, $final, $actor);
        $url = route('backend.selection-import.index', ['event' => $target, 'source_event_id' => $source->id, 'choose_regions' => 1, 'review_regions' => 1, 'source_region_ids' => [$region->id]]);
        $response = $this->get($url)->assertOk()->assertSee('u/10 boys')->assertDontSee('u/12 girls')->assertDontSee($secondPlayer->name);
        if (getenv('CT_SELECTION_IMPORT_BROWSER_FIXTURE')) file_put_contents(storage_path('app/testing/selection-import-selected-regions.html'), $response->getContent());
        $input = ['finalization_id' => $final->id, 'source_region_ids' => [$region->id], 'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1];
        $store = route('backend.selection-import.store', $target);
        $this->postJson($store, array_replace($input, ['mapping' => ['10-boys' => 0, '12-girls' => 0]]))->assertUnprocessable()->assertJsonValidationErrors('mapping');
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
        $this->post($store, $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($store, $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('event_selection_roster_imports', 1);
        $this->assertDatabaseCount('no_profile_team_players', 2);
        $import = EventSelectionRosterImport::firstOrFail();
        $this->assertSame(['10-boys'], array_keys($import->groups));
        $this->assertSame([$region->id], $import->groups['10-boys']['source_region_ids']);
        $this->assertSame([1, 2], array_column($import->groups['10-boys']['players'], 'rank'));
        $this->postJson($store, array_replace($input, ['source_region_ids' => [$secondRegion->id], 'mapping' => ['12-girls' => 0]]))->assertUnprocessable()->assertJsonValidationErrors('source_region_ids');
        $this->assertDatabaseCount('event_selection_roster_imports', 1);
        $this->assertDatabaseCount('no_profile_team_players', 2);
        $this->assertSame($import->groups, $import->fresh()->groups);
    }

    public function test_partial_combined_finalized_region_cohort_is_rejected_without_splitting_players(): void
    {
        [$source, $target, $final, $region, $actor] = $this->scenario();
        $second = TeamRegion::create(['region_name' => 'Second combined region']);
        $source->regions()->attach($second);
        $groups = $final->groups;
        $groups['10-boys']['region_ids'] = [$region->id, $second->id];
        DB::table('event_selection_finalizations')->where('id', $final->id)->update(['groups' => json_encode($groups)]);
        $input = ['finalization_id' => $final->id, 'source_region_ids' => [$region->id], 'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1];
        $this->postJson(route('backend.selection-import.store', $target), $input)->assertUnprocessable()->assertJsonValidationErrors('source_region_ids');
        $this->assertDatabaseCount('no_profile_team_players', 0);
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
        $this->post(route('backend.selection-import.store', $target), array_replace($input, ['source_region_ids' => [$second->id, $region->id]]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('no_profile_team_players', 2);
        $this->assertSame(['main', 'reserve'], array_column(EventSelectionRosterImport::first()->groups['10-boys']['players'], 'status'));
    }

    public function test_foreign_unfinalized_duplicate_and_empty_source_regions_cannot_be_imported(): void
    {
        [$source, $target, $final, $region] = $this->scenario();
        $unfinalized = TeamRegion::create(['region_name' => 'No selection']);
        $source->regions()->attach($unfinalized);
        $foreign = TeamRegion::create(['region_name' => 'Foreign event region']);
        $input = ['finalization_id' => $final->id, 'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1];
        foreach ([[], [$foreign->id], [$unfinalized->id], [$region->id, $region->id]] as $ids) {
            $this->postJson(route('backend.selection-import.store', $target), $input + ['source_region_ids' => $ids])->assertUnprocessable();
        }
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
        $this->assertDatabaseCount('no_profile_team_players', 0);
    }

    public function test_frozen_region_cohort_is_used_even_if_source_team_region_changes(): void
    {
        [$source, $target, $final, $region, $actor, $players, $team] = $this->scenario();
        $newRegion = TeamRegion::create(['region_name' => 'Changed team region']);
        $source->regions()->attach($newRegion);
        $groups = $final->groups;
        $groups['10-boys']['region_ids'] = [$region->id];
        DB::table('event_selection_finalizations')->where('id', $final->id)->update(['groups' => json_encode($groups)]);
        $team->update(['region_id' => $newRegion->id]);
        $service = app(\App\Services\EventSelectionRosterImportService::class);
        $preview = $service->preview($target, $final->fresh(), $actor, [$region->id]);
        $this->assertSame([$region->id], $preview['groups']['10-boys']['source_region_ids']);
        $this->assertArrayNotHasKey($newRegion->id, $preview['regionGroups']);
        $this->assertSame([(string)$players[1]->id, (string)$players[0]->id], array_column($preview['groups']['10-boys']['players'], 'key'));
    }

    private function addSecondRegionGroup(Event $source, EventSelectionFinalization $final, User $actor): array
    {
        $region = TeamRegion::create(['region_name' => 'Second source region']);
        $source->regions()->attach($region);
        $category = Category::factory()->create(['name' => 'u/12 Girls']);
        $categoryEvent = CategoryEvent::factory()->create(['event_id' => $source->id, 'category_id' => $category->id]);
        $team = new Team(['name' => 'Second u/12 Girls', 'category_event_id' => $categoryEvent->id, 'region_id' => $region->id, 'num_team_members' => 1, 'published' => true, 'year' => 2026]);
        $team->forceFill(['user_id' => $actor->id, 'personal_team' => false])->save();
        $player = Player::factory()->create();
        $groups = $final->groups;
        $groups['12-girls'] = ['region_ids' => [$region->id], 'snapshot' => [['id' => $player->id, 'name' => $player->name.' '.$player->surname, 'source_team_ids' => [$team->id], 'source_participants' => [['team_id' => $team->id, 'type' => 'profile', 'id' => $player->id]]]],
            'selected_keys' => [(string)$player->id], 'selection_statuses' => [(string)$player->id => ['position' => 1, 'status' => 'main']]];
        DB::table('event_selection_finalizations')->where('id', $final->id)->update(['groups' => json_encode($groups)]);
        return [$region, $player];
    }

    private function scenario(): array
    {
        Role::findOrCreate('admin', 'web');
        $actor = User::factory()->create()->assignRole('admin');
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Selection import test', 'code' => 'selection-import-test', 'type' => EventType::TEAM]);
        $source = Event::factory()->create(['name' => 'Platteland selection source', 'eventType' => $type, 'results_published' => false]);
        $target = Event::factory()->create(['name' => 'Interdistricts destination', 'eventType' => $type, 'results_published' => false]);
        $actor->adminEvents()->attach([$source->id, $target->id]); $this->actingAs($actor);
        $region = TeamRegion::create(['region_name' => 'Platteland', 'short_name' => 'PL']);
        (new EventRegion)->forceFill(['event_id' => $target->id, 'region_id' => $region->id, 'ordering' => 1])->save();
        $source->regions()->attach($region);
        $category = Category::factory()->create(['name' => 'u/10 Boys']);
        $categoryEvent = CategoryEvent::factory()->create(['event_id' => $source->id, 'category_id' => $category->id]);
        $team = new Team(['name' => 'Source u/10 Boys', 'category_event_id' => $categoryEvent->id, 'region_id' => $region->id, 'num_team_members' => 3, 'published' => true, 'year' => 2026]);
        $team->forceFill(['user_id' => $actor->id, 'personal_team' => false])->save();
        $players = Player::factory()->count(3)->create();
        $snapshot = $players->map(fn ($player, $index) => ['id' => $player->id, 'name' => $player->name.' '.$player->surname, 'source_team_ids' => [$team->id], 'source_participants' => [['team_id' => $team->id, 'type' => 'profile', 'id' => $player->id]], 'draft_position' => $index + 1])->all();
        $final = EventSelectionFinalization::create(['event_id' => $source->id, 'finalized_by' => $actor->id, 'draft_versions' => ['10-boys' => 1], 'fingerprint' => str_repeat('a', 64), 'team_size' => 1, 'reserve_count' => 1, 'finalized_at' => now(),
            'groups' => ['10-boys' => ['snapshot' => $snapshot, 'selected_keys' => [(string)$players[1]->id, (string)$players[0]->id], 'selection_statuses' => [(string)$players[1]->id => ['position' => 1, 'status' => 'main'], (string)$players[0]->id => ['position' => 2, 'status' => 'reserve']], 'reasons' => ['Private selection note']]]]);
        return [$source, $target, $final, $region, $actor, $players, $team];
    }
}
