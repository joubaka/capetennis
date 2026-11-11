<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventNomination;
use App\Models\EventSelectionFinalization;
use App\Models\EventType;
use App\Models\NoProfileTeamPlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamFixture;
use App\Models\TeamRegion;
use App\Models\TeamSelectionImport;
use App\Models\TeamSelectionInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CavaliersTrialRosterDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_roster_edits_persist_in_order_without_touching_public_nominations_or_entries(): void
    {
        Mail::fake();
        Queue::fake();
        [$event, $division] = $this->division();
        $otherDivision = $this->division($event)[1];
        $this->actingAs($this->administrator($event));
        [$first, $second, $reserve, $legacy] = Player::factory()->count(4)->create()->all();
        EventNomination::create([
            'event_id' => $event->id,
            'category_event_id' => $division->id,
            'player_id' => $legacy->id,
        ]);
        $division->update(['nominations_published' => true]);

        $url = route('backend.cavaliers-roster.show', [$event, $division]);
        $this->getJson($url)->assertJsonPath('revision', null)->assertJsonPath('selected', [])->assertJsonPath('reserves', [])->assertJsonPath('declined', []);
        $saved = $this->putJson($url, [
            'revision' => null,
            'selected' => [$first->id, $second->id],
            'reserves' => [$reserve->id],
        ])->assertOk();
        $this->getJson($url)->assertJsonPath('selected.0.id', $first->id)
            ->assertJsonPath('selected.1.id', $second->id)->assertJsonPath('reserves.0.id', $reserve->id);
        $this->putJson($url, [
            'revision' => $saved->json('revision'),
            'selected' => [$reserve->id, $second->id],
            'reserves' => [$first->id],
        ])->assertOk();
        $this->getJson($url)->assertJsonPath('selected.0.id', $reserve->id)->assertJsonPath('reserves.0.id', $first->id);
        $this->getJson(route('backend.cavaliers-roster.show', [$event, $otherDivision]))
            ->assertJsonPath('revision', null)->assertJsonPath('selected', [])->assertJsonPath('reserves', [])->assertJsonPath('declined', []);

        $this->assertDatabaseCount('event_nominations', 1);
        $this->assertDatabaseHas('event_nominations', ['player_id' => $legacy->id, 'category_event_id' => $division->id]);
        $this->assertTrue((bool) $division->fresh()->nominations_published);
        $this->assertDatabaseCount('category_event_registrations', 0);
        $this->assertDatabaseCount('registration_orders', 0);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_saved_roster_publishes_reviewed_changes_and_unpublishes_without_losing_nominees(): void
    {
        Mail::fake();
        Queue::fake();
        [$event, $division] = $this->division();
        [$otherEvent, $otherDivision] = $this->division();
        [$first, $second, $reserve, $legacy] = Player::factory()->count(4)->create()->all();
        $this->actingAs($this->administrator($event));
        $draftUrl = route('backend.cavaliers-roster.show', [$event, $division]);
        $url = route('backend.cavaliers-roster.nominations', [$event, $division]);
        $page = route('events.show', $event);
        $legacyNomination = EventNomination::create(['event_id' => $event->id, 'category_event_id' => $division->id, 'player_id' => $legacy->id]);
        $division->update(['nominations_published' => true]);

        $revision = $this->putJson($draftUrl, [
            'revision' => null, 'selected' => [$second->id, $first->id], 'reserves' => [$reserve->id],
            'declined' => [$legacy->id],
        ])->assertOk()->json('revision');
        $publication = $this->getJson($draftUrl)->assertJsonPath('declined.0.id', $legacy->id)
            ->assertJsonPath('publication.players.0.id', $legacy->id)->json('publication');
        $this->get($page)->assertOk()->assertSee($legacy->full_name)->assertDontSee($first->full_name);

        $publish = ['revision' => $revision, 'publish' => true, 'fingerprint' => $publication['fingerprint']];
        $this->postJson(route('backend.cavaliers-roster.nominations', [$event, $otherDivision]), $publish)->assertNotFound();
        $this->postJson(route('backend.cavaliers-roster.nominations', [$otherEvent, $otherDivision]), $publish)->assertForbidden();
        $this->postJson($url, array_replace($publish, ['fingerprint' => str_repeat('a', 64)]))->assertConflict();
        $this->assertModelExists($legacyNomination);

        $published = $this->postJson($url, $publish)->assertOk()->assertJsonPath('published', true)->json();
        $this->assertSame([$second->id, $first->id], array_column($published['players'], 'id'));
        $this->get($page)->assertOk()->assertSeeInOrder([$second->full_name, $first->full_name])
            ->assertDontSee($reserve->full_name)->assertDontSee($legacy->full_name);
        $this->postJson($url, $publish)->assertConflict();
        $unpublished = $this->postJson($url, ['publish' => false, 'fingerprint' => $published['fingerprint']])
            ->assertOk()->assertJsonPath('published', false)->json();
        $this->assertSame($published['players'], $unpublished['players']);
        $this->get($page)->assertOk()->assertDontSee($first->full_name);
        $this->getJson($draftUrl)->assertJsonPath('revision', $revision)->assertJsonPath('declined.0.id', $legacy->id);
        $this->assertDatabaseCount('event_nominations', 2);
        $this->assertDatabaseCount('category_event_registrations', 0);
        $foreign = EventNomination::create(['event_id' => $otherEvent->id, 'category_event_id' => $division->id, 'player_id' => $reserve->id]);
        $review = $this->getJson($draftUrl)->assertOk()->assertJsonCount(2, 'publication.players')->json('publication');
        $this->postJson($url, array_replace($publish, ['fingerprint' => $review['fingerprint']]))->assertUnprocessable();
        $this->assertModelExists($foreign);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_rejects_duplicates_over_capacity_missing_players_and_stale_revisions(): void
    {
        [$event, $division] = $this->division();
        $this->actingAs($this->administrator($event));
        $ids = Player::factory()->count(38)->create()->modelKeys();
        $url = route('backend.cavaliers-roster.update', [$event, $division]);
        $empty = ['revision' => null, 'selected' => [], 'reserves' => []];
        $this->putJson($url, array_replace($empty, ['selected' => array_slice($ids, 0, 33)]))
            ->assertUnprocessable()->assertJsonValidationErrors('selected');
        $this->putJson($url, array_replace($empty, ['reserves' => array_slice($ids, 0, 6)]))
            ->assertUnprocessable()->assertJsonValidationErrors('reserves');
        $this->putJson($url, array_replace($empty, ['selected' => [$ids[0]], 'reserves' => [$ids[0]]]))
            ->assertUnprocessable()->assertJsonValidationErrors('selected');
        $this->putJson($url, array_replace($empty, ['selected' => [max($ids) + 1]]))
            ->assertUnprocessable()->assertJsonValidationErrors('selected');
        $this->assertDatabaseCount('cavaliers_trial_roster_drafts', 0);

        $full = $this->putJson($url, [
            'revision' => null,
            'selected' => array_slice($ids, 0, 32),
            'reserves' => array_slice($ids, 32, 5),
        ])->assertOk();
        $this->putJson($url, $empty)->assertConflict();
        $this->putJson($url, array_replace($empty, ['revision' => $full->json('revision')]))->assertOk();
        $this->putJson($url, array_replace($empty, ['revision' => $full->json('revision'), 'selected' => [$ids[0]]]))
            ->assertConflict();
        $this->getJson($url)->assertJsonPath('selected', [])->assertJsonPath('reserves', []);
    }

    public function test_private_routes_require_event_authority_and_scope_search_to_names(): void
    {
        [$event, $division] = $this->division();
        [$foreignEvent, $foreignDivision] = $this->division();
        [$otherType, $otherDivision] = $this->division(Event::factory()->create(['eventType' => 1]));
        $player = Player::factory()->create(['name' => 'Synthetic', 'surname' => 'Cavalier']);
        $url = route('backend.cavaliers-roster.show', [$event, $division]);
        $search = route('backend.cavaliers-roster.players', [$event, $division, 'q' => 'cavalier SYN']);
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($this->administrator($foreignEvent))->getJson($url)->assertForbidden();
        $this->getJson($search)->assertForbidden();
        $this->putJson($url, ['revision' => null, 'selected' => [], 'reserves' => []])->assertForbidden();
        $this->actingAs($this->administrator($event));
        $this->getJson(route('backend.cavaliers-roster.show', [$event, $foreignDivision]))->assertNotFound();
        $this->getJson(route('backend.cavaliers-roster.show', [$otherType, $otherDivision]))->assertNotFound();
        $this->getJson($search)->assertOk()->assertExactJson([['id' => $player->id, 'name' => $player->full_name]]);
        $this->assertDatabaseCount('cavaliers_trial_roster_drafts', 0);
    }

    public function test_candidate_setup_is_private_per_division_and_separates_regions_from_result_exclusions(): void
    {
        [$event, $division] = $this->division(Event::factory()->create(['id' => 252, 'eventType' => 5]), 'U11 Girls');
        $event->team_result_settings = ['team_size' => 9];
        $event->save();
        $otherDivision = $this->division($event)[1];
        $teamType = EventType::forceCreate(['id' => 242, 'name' => 'Team trial source', 'type' => EventType::TEAM]);
        $source = Event::factory()->create(['id' => 242, 'name' => 'Inter-Districts Tournament Primary Schools 2026', 'eventType' => $teamType->id]);
        $sourceDivision = $this->division($source)[1];
        [$first, $second, $losing, $reserve, $drakenstein, $eden, $unpublished] = Player::factory()->count(7)->create()->all();
        $cavaliersTeam = $this->candidateTeam($sourceDivision, 'Cavaliers Platteland 2026', [1 => $first, 2 => $second, 5 => $losing, 11 => $reserve]);
        $drakensteinTeam = $this->candidateTeam($sourceDivision, 'Drakenstein 2026', [1 => $drakenstein]);
        $edenTeam = $this->candidateTeam($sourceDivision, 'Eden 2026', [1 => $eden]);
        $unpublishedTeam = $this->candidateTeam($sourceDivision, 'Winelands 2026', [1 => $unpublished], published: false);
        $draw = Draw::factory()->create([
            'event_id' => $source->id, 'category_event_id' => $sourceDivision->id, 'published' => true,
        ]);
        $this->candidateMatch($draw, $cavaliersTeam, $drakensteinTeam, $first, $drakenstein, 6, 2);
        $this->candidateMatch($draw, $cavaliersTeam, $drakensteinTeam, $losing, $drakenstein, 2, 6);
        $this->candidateMatch($draw, $cavaliersTeam, $edenTeam, $second, $eden, 2, 6);

        $setup = route('backend.cavaliers-roster.setup', [$event, $division]);
        $review = route('backend.cavaliers-roster.candidates', [$event, $division]);
        $cavaliers = (int) $cavaliersTeam->region_id;
        $drak = (int) $drakensteinTeam->region_id;
        $edenId = (int) $edenTeam->region_id;
        $winelands = (int) $unpublishedTeam->region_id;
        $defaults = ['region_ids' => [$cavaliers, $drak, $winelands], 'excluded_result_region_ids' => [$edenId]];
        $custom = ['region_ids' => [$cavaliers, $edenId], 'excluded_result_region_ids' => [$cavaliers]];

        $this->actingAs($this->administrator($source))->getJson($review)->assertForbidden();
        $this->putJson($setup, $custom)->assertForbidden();
        $actor = $this->administrator($event);
        $this->actingAs($actor)->getJson(route('backend.cavaliers-roster.candidates', [$event, $sourceDivision]))->assertNotFound();
        $this->getJson($setup)->assertOk()
            ->assertJsonPath('region_ids', $defaults['region_ids'])
            ->assertJsonPath('excluded_result_region_ids', $defaults['excluded_result_region_ids']);
        $this->putJson($setup, ['region_ids' => [99999], 'excluded_result_region_ids' => []])->assertUnprocessable();
        $this->putJson($setup, ['region_ids' => [$cavaliers], 'excluded_result_region_ids' => [99999]])->assertUnprocessable();

        $this->getJson($review)->assertOk()
            ->assertJsonPath('source_division', 'U10 Girls')
            ->assertJsonPath('missing_regions', ['Winelands 2026'])
            ->assertJsonPath('candidates.0.id', $first->id)
            ->assertJsonPath('candidates.0.source', 'Inter-Districts')
            ->assertJsonPath('candidates.0.wins', 1)
            ->assertJsonPath('candidates.0.lost_all', false)
            ->assertJsonPath('candidates.0.only_beat_winless', false)
            ->assertJsonPath('candidates.0.starting_credit', 1)
            ->assertJsonPath('candidates.0.matches', [[
                'opponent' => $drakenstein->full_name, 'outcome' => 'Won', 'playing_number' => 1, 'score' => '6–2, 6–2', 'format' => 'Singles',
            ]])
            ->assertJsonPath('candidates.1.id', $drakenstein->id)
            ->assertJsonPath('candidates.1.only_beat_winless', true)
            ->assertJsonPath('candidates.2.id', $second->id)
            ->assertJsonPath('candidates.2.wins', null)
            ->assertJsonPath('candidates.2.matches', [])
            ->assertJsonPath('candidates.2.lost_all', false)
            ->assertJsonPath('candidates.2.only_beat_winless', false)
            ->assertJsonPath('candidates.3.id', $losing->id)
            ->assertJsonPath('candidates.3.rank', 5)
            ->assertJsonPath('candidates.3.lost_all', true)
            ->assertJsonPath('candidates.3.matches', [[
                'opponent' => $drakenstein->full_name, 'outcome' => 'Lost', 'playing_number' => 5, 'score' => '2–6, 2–6', 'format' => 'Singles',
            ]])
            ->assertJsonCount(4, 'candidates');
        $this->assertSame(['team_size' => 9], $event->fresh()->team_result_settings);

        $draw->update(['published' => false]);
        $rows = collect($this->getJson($review)->assertOk()->json('candidates'));
        $this->assertTrue($rows->every(fn (array $row) => $row['wins'] === null && $row['matches'] === []));
        $draw->update(['published' => true]);

        $this->putJson($setup, ['region_ids' => [$cavaliers], 'excluded_result_region_ids' => [$edenId]])->assertOk();
        $this->getJson($review)->assertOk()
            ->assertJsonPath('candidates.0.id', $first->id)
            ->assertJsonPath('candidates.0.only_beat_winless', false)
            ->assertJsonCount(3, 'candidates');

        $this->putJson($setup, $custom)->assertOk()->assertExactJson($custom);
        $this->getJson($setup)->assertOk()->assertJsonPath('region_ids', $custom['region_ids'])
            ->assertJsonPath('excluded_result_region_ids', $custom['excluded_result_region_ids']);
        $this->getJson(route('backend.cavaliers-roster.setup', [$event, $otherDivision]))->assertOk()
            ->assertJsonPath('region_ids', $defaults['region_ids'])
            ->assertJsonPath('excluded_result_region_ids', $defaults['excluded_result_region_ids']);
        $rows = collect($this->getJson($review)->assertOk()->assertJsonCount(4, 'candidates')->json('candidates'));
        $this->assertEqualsCanonicalizing([$first->id, $second->id, $losing->id, $eden->id], $rows->pluck('id')->all());
        $this->assertTrue($rows->every(fn (array $row) => $row['wins'] === null));
        EventAdmin::create(['event_id' => $source->id, 'user_id' => $actor->id]);
        $this->getJson($review)->assertOk()->assertJsonCount(5, 'candidates')
            ->assertJsonPath('candidates.4.id', $reserve->id)
            ->assertJsonPath('candidates.4.list_status', 'Inter-Districts reserve')
            ->assertJsonPath('candidates.4.wins', null)
            ->assertJsonPath('candidates.4.matches', []);
        $this->assertNull($source->fresh()->team_result_settings);
        $this->assertSame(9, $event->fresh()->team_result_settings['team_size']);
        $this->assertDatabaseCount('cavaliers_trial_roster_drafts', 0);
        $this->assertDatabaseCount('event_nominations', 0);

        $pdf = route('backend.cavaliers-roster.pdf', [$event, $division]);
        $this->get($pdf)->assertNotFound();
        $this->putJson($setup, $defaults)->assertOk();
        $this->putJson(route('backend.cavaliers-roster.update', [$event, $division]), [
            'revision' => null, 'selected' => [$first->id], 'reserves' => [],
        ])->assertOk();
        $download = $this->get($pdf)->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload('cavaliers-trials-u11-girls.pdf');
        $this->assertStringContainsString('no-store', $download->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF-', $download->getContent());
        $this->actingAs($this->administrator($source))->get($pdf)->assertForbidden();
        $this->actingAs($actor)->get(route('backend.cavaliers-roster.pdf', [$event, $sourceDivision]))->assertNotFound();
    }

    public function test_platteland_step_requires_source_access_and_appends_only_frozen_reserves_then_additional_players(): void
    {
        [$event, $division] = $this->division(Event::factory()->create(['id' => 252, 'eventType' => 5]), 'U11 Girls');
        $teamType = EventType::forceCreate(['id' => 242, 'name' => 'Team trial source', 'type' => EventType::TEAM]);
        $inter = Event::factory()->create(['id' => 242, 'name' => 'Inter-Districts Tournament Primary Schools 2026', 'eventType' => $teamType->id]);
        $source = Event::factory()->create(['id' => 241, 'name' => 'Platteland Tennis Tournament Primary Schools 2026', 'eventType' => $teamType->id]);
        [$interPlayer, $main, $firstReserve, $linkedReserve, $staleLink] = Player::factory()->count(5)->create()->all();
        $this->candidateTeam($this->division($inter)[1], 'Cavaliers Platteland 2026', [1 => $interPlayer]);
        $sourceTeam = $this->candidateTeam($this->division($source)[1], 'Overberg Primary Schools 2026', [1 => $main, 2 => $firstReserve]);
        $additional = Player::factory()->count(21)->create();
        $profile = fn (Player $player, int $position) => [
            'id' => $player->id, 'draft_position' => $position, 'wins' => 2, 'losses' => 1, 'band' => '1–2', 'rank' => 1,
            'source_participants' => [['team_id' => $sourceTeam->id, 'type' => 'profile', 'id' => $player->id, 'profile_id' => $player->id]],
            'matches' => [
                ['opponent' => 'Synthetic opponent', 'won' => false, 'score' => '6–4, 6–2', 'format' => 'reverse_singles',
                    'opponent_id' => 'PRIVATE_MATCH_EVIDENCE'],
                ['opponent' => 'Another opponent', 'won' => true, 'score' => 'PRIVATE_SCORE_NOTE'],
                'PRIVATE_MALFORMED_MATCH',
            ],
        ];
        $imported = function (Player $player, int $position) use ($sourceTeam) {
            $slot = NoProfileTeamPlayer::create(['team_id' => $sourceTeam->id, 'name' => $player->name, 'surname' => $player->surname,
                'rank' => $position, 'pay_status' => 0, 'player_profile' => $player->id]);

            return [
                'id' => 'imported:'.$sourceTeam->id.':'.$slot->id,
                'name' => trim($player->name.' '.$player->surname),
                'draft_position' => $position,
                'source_participants' => [['team_id' => $sourceTeam->id, 'type' => 'imported', 'id' => $slot->id, 'profile_id' => $player->id]],
            ];
        };
        $lockedMain = $imported($main, 1);
        $lockedReserve = $imported($linkedReserve, 3) + ['wins' => 0, 'losses' => 1];
        $unselectedStaleLink = $imported($staleLink, 4);
        foreach ([$lockedMain, $unselectedStaleLink] as $changed) {
            NoProfileTeamPlayer::findOrFail($changed['source_participants'][0]['id'])->update(['player_profile' => $interPlayer->id]);
        }
        $snapshot = [
            $profile($interPlayer, 10), $lockedReserve, $profile($main, 5),
            $profile($firstReserve, 2), $unselectedStaleLink, $lockedMain,
            ...$additional->map(fn (Player $player, int $index) => array_replace($profile($player, 40 - $index), $index === 0 ? [
                'wins' => 1, 'losses' => 0,
                'matches' => [['won' => true, 'opponent_id' => $lockedReserve['id'], 'opponent' => $lockedReserve['name']]],
            ] : []))->all(),
        ];
        $actor = $this->administrator($event);
        $finalized = EventSelectionFinalization::create([
            'event_id' => $source->id, 'finalized_by' => $actor->id, 'draft_versions' => ['10-girls' => 1],
            'groups' => ['10-girls' => [
                'draft_version' => 1, 'selected_keys' => [$lockedReserve['id'], $lockedMain['id'], (string) $firstReserve->id],
                'snapshot' => $snapshot, 'reasons' => ['PRIVATE_SELECTION_COMMENT'],
            ]],
            'team_size' => 1, 'reserve_count' => 2, 'fingerprint' => str_repeat('a', 64), 'finalized_at' => now(),
        ]);
        $url = route('backend.cavaliers-roster.candidates', [$event, $division]);

        $this->actingAs($actor)->getJson($url)->assertOk()
            ->assertJsonCount(1, 'candidates')->assertJsonPath('candidates.0.id', $interPlayer->id)
            ->assertDontSee('Synthetic opponent')
            ->assertJsonPath('platteland_notice', 'You need access to the Platteland selection preview to review Step 2.');

        EventAdmin::create(['event_id' => $source->id, 'user_id' => $actor->id]);
        $response = $this->getJson($url)->assertOk()
            ->assertJsonPath('platteland_notice', null)
            ->assertJsonPath('candidates.1.list_status', 'Locked reserve')
            ->assertJsonPath('candidates.1.matches', [
                ['opponent' => 'Synthetic opponent', 'outcome' => 'Lost', 'playing_number' => null, 'score' => '6–4, 6–2', 'format' => 'Reverse singles'],
                ['opponent' => 'Another opponent', 'outcome' => 'Won', 'playing_number' => null, 'score' => null, 'format' => null],
            ])
            ->assertJsonPath('candidates.2.list_status', 'Locked reserve')
            ->assertJsonPath('candidates.2.lost_all', true)
            ->assertJsonPath('candidates.3.list_status', 'Additional candidate')
            ->assertJsonPath('candidates.3.only_beat_winless', true)
            ->assertDontSee('PRIVATE_MATCH_EVIDENCE')->assertDontSee('PRIVATE_SELECTION_COMMENT')
            ->assertDontSee('PRIVATE_SCORE_NOTE')->assertDontSee('PRIVATE_MALFORMED_MATCH');
        $this->assertSame([$interPlayer->id, $firstReserve->id, $linkedReserve->id, ...$additional->modelKeys()],
            array_column($response->json('candidates'), 'id'));
        $this->assertDatabaseCount('cavaliers_trial_roster_drafts', 0);
        $this->assertDatabaseCount('event_nominations', 0);
        // MySQL JSON normalizes object key order; compare the snapshot's values and indexed positions.
        $this->assertEquals($snapshot, $finalized->fresh()->groups['10-girls']['snapshot']);
    }

    public function test_u13_girls_missing_finalized_main_candidates_require_both_sources_and_are_opt_in(): void
    {
        [$event, $division] = $this->division(Event::factory()->create(['id' => 252, 'eventType' => 5]), 'U13 Girls');
        $type = EventType::forceCreate(['id' => 242, 'name' => 'Team trial source', 'type' => EventType::TEAM]);
        $inter = Event::factory()->create(['id' => 242, 'name' => 'Inter-Districts Tournament Primary Schools 2026', 'eventType' => $type->id]);
        $platteland = Event::factory()->create(['id' => 241, 'name' => 'Platteland Tennis Tournament Primary Schools 2026', 'eventType' => $type->id]);
        [$main, $declined, $missing, $assigned, $reserve] = Player::factory()->count(5)->create()->all();
        $interDivision = $this->division($inter, 'U12 Girls')[1];
        $interTeam = $this->candidateTeam($interDivision, 'Cavaliers Platteland 2026', [1 => $main]);
        $sourceTeam = $this->candidateTeam($this->division($platteland, 'U12 Girls')[1], 'Overberg 2026', [1 => $main]);
        $actor = $this->administrator($event);
        EventAdmin::create(['event_id' => $platteland->id, 'user_id' => $actor->id]);
        $players = collect([$main, $declined, $missing, $assigned, $reserve]);
        $snapshot = $players->map(fn (Player $player, int $index) => [
            'id' => $player->id, 'position' => $index + 1,
            'source_participants' => [['team_id' => $sourceTeam->id, 'type' => 'profile', 'id' => $player->id, 'profile_id' => $player->id]],
        ]);
        EventSelectionFinalization::create([
            'event_id' => $platteland->id, 'finalized_by' => $actor->id, 'draft_versions' => ['12-girls' => 1],
            'groups' => ['12-girls' => ['draft_version' => 1, 'selected_keys' => $players->pluck('id')->all(), 'snapshot' => $snapshot->all()]],
            'team_size' => 4, 'reserve_count' => 1, 'fingerprint' => str_repeat('a', 64), 'finalized_at' => now(),
        ]);
        $draw = Draw::factory()->create(['event_id' => $inter->id, 'category_event_id' => $interDivision->id, 'published' => true]);
        $this->candidateMatch($draw, $interTeam, $interTeam, $assigned, $main, 6, 1);
        $import = TeamSelectionImport::create([
            'source_id' => 1, 'event_id' => $inter->id, 'region_id' => $interTeam->region_id,
            'series_id' => 1, 'ranking_run_id' => 'cavaliers-source', 'status' => 'sent',
        ]);
        TeamSelectionInvitation::create([
            'import_id' => $import->id, 'event_id' => $inter->id, 'region_id' => $interTeam->region_id,
            'team_id' => $interTeam->id, 'player_id' => $declined->id, 'ranking_list_id' => 1, 'ranking_position' => 1, 'queue_position' => 1,
            'status' => TeamSelectionInvitation::DECLINED, 'declined_at' => now(), 'decline_method' => 'authenticated_invitation',
            'decline_reason' => 'PRIVATE_DECLINE_REASON',
        ]);
        $url = route('backend.cavaliers-roster.candidates', [$event, $division]);
        $this->actingAs($actor)->getJson($url)->assertOk()->assertJsonCount(2, 'candidates');
        EventAdmin::create(['event_id' => $inter->id, 'user_id' => $actor->id]);
        $response = $this->getJson($url)->assertOk()->assertJsonCount(4, 'candidates')->assertDontSee('PRIVATE_DECLINE_REASON')
            ->assertJsonPath('candidates.2.opt_in', true)
            ->assertJsonPath('candidates.2.list_status', 'Locked main team · No Inter-Districts participation recorded · Confirmed Inter-Districts decline')
            ->assertJsonPath('candidates.3.opt_in', true)
            ->assertJsonPath('candidates.3.list_status', 'Locked main team · No Inter-Districts participation recorded · No current decline recorded');
        $this->assertSame([$main->id, $reserve->id, $declined->id, $missing->id], array_column($response->json('candidates'), 'id'));
        $this->assertDatabaseCount('cavaliers_trial_roster_drafts', 0);
    }

    private function candidateMatch(Draw $draw, Team $home, Team $away, Player $first, Player $second, int $homeScore, int $awayScore): void
    {
        $fixture = TeamFixture::create([
            'draw_id' => $draw->id, 'fixture_type' => 1, 'age' => 'U10 Girls', 'numSets' => 3,
            'match_nr' => 1, 'round_nr' => 1, 'region1' => $home->region_id, 'region2' => $away->region_id,
        ]);
        $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => $first->id, 'team2_id' => $second->id]);
        foreach ([1, 2] as $set) {
            $fixture->teamResults()->create(['set_nr' => $set, 'team1_score' => $homeScore, 'team2_score' => $awayScore]);
        }
    }

    private function candidateTeam(CategoryEvent $division, string $name, array $players, int $capacity = 10, bool $published = true): Team
    {
        $region = TeamRegion::create(['region_name' => $name]);
        $division->event->regions()->attach($region);
        $team = Team::factory()->create([
            'category_event_id' => $division->id, 'region_id' => $region->id, 'name' => $name,
            'num_team_members' => $capacity, 'published' => $published, 'noProfile' => false,
        ]);
        foreach ($players as $rank => $player) {
            $team->team_players()->create(['player_id' => $player->id, 'rank' => $rank]);
        }

        return $team;
    }

    private function division(?Event $event = null, string $name = 'U10 Girls'): array
    {
        $event ??= Event::factory()->create(['eventType' => 5]);
        $division = CategoryEvent::factory()->create([
            'event_id' => $event->id,
            'category_id' => Category::factory()->create(['name' => $name])->id,
        ]);

        return [$event, $division];
    }

    private function administrator(Event $event): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('admin', 'web'));
        EventAdmin::create(['event_id' => $event->id, 'user_id' => $user->id]);

        return $user;
    }
}
