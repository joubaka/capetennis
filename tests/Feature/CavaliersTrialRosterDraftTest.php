<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventNomination;
use App\Models\EventType;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamFixture;
use App\Models\TeamRegion;
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
        $this->getJson($url)->assertExactJson(['revision' => null, 'selected' => [], 'reserves' => []]);
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
            ->assertExactJson(['revision' => null, 'selected' => [], 'reserves' => []]);

        $this->assertDatabaseCount('event_nominations', 1);
        $this->assertDatabaseHas('event_nominations', ['player_id' => $legacy->id, 'category_event_id' => $division->id]);
        $this->assertTrue((bool) $division->fresh()->nominations_published);
        $this->assertDatabaseCount('category_event_registrations', 0);
        $this->assertDatabaseCount('registration_orders', 0);
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

    public function test_candidate_review_uses_public_rosters_and_counted_results_without_saving_a_draft(): void
    {
        [$event, $division] = $this->division(Event::factory()->create(['id' => 252, 'eventType' => 5]));
        $teamType = EventType::forceCreate(['id' => 242, 'name' => 'Team trial source', 'type' => EventType::TEAM]);
        $interDistricts = Event::factory()->create(['id' => 242, 'name' => 'Inter-Districts Tournament Primary Schools 2026', 'eventType' => $teamType->id]);
        $platteland = Event::factory()->create(['id' => 241, 'name' => 'Platteland Tennis Tournament Primary Schools 2026', 'eventType' => $teamType->id]);
        $interDivision = $this->division($interDistricts)[1];
        $plattelandDivision = $this->division($platteland)[1];
        [$first, $reserve, $otherDistrict, $higher, $lower, $opponent, $hidden] = Player::factory()->count(7)->create()->all();
        $this->candidateTeam($interDivision, 'Cavaliers Platteland 2026', [1 => $first, 2 => $reserve], capacity: 1);
        $this->candidateTeam($interDivision, 'Another district', [1 => $otherDistrict]);
        $overberg = $this->candidateTeam($plattelandDivision, 'Overberg Primary Schools 2026', [
            1 => $higher, 2 => $reserve, 3 => $lower, 4 => $first, 5 => $otherDistrict,
        ]);
        $westCoast = $this->candidateTeam($plattelandDivision, 'West Coast Primary Schools 2026', [1 => $opponent]);
        $this->candidateTeam($plattelandDivision, 'Witzenberg/Breede Vallei/Langeberg Primary Schools 2026', [1 => $hidden], published: false);
        $draw = Draw::factory()->create([
            'event_id' => $platteland->id, 'category_event_id' => $plattelandDivision->id, 'published' => true,
        ]);
        $fixture = TeamFixture::create([
            'draw_id' => $draw->id, 'fixture_type' => 1, 'age' => 'U10 Girls', 'numSets' => 3,
            'match_nr' => 1, 'round_nr' => 1,
            'region1' => $overberg->region_id, 'region2' => $westCoast->region_id,
        ]);
        $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => $higher->id, 'team2_id' => $opponent->id]);
        foreach ([1, 2] as $set) {
            $fixture->teamResults()->create(['set_nr' => $set, 'team1_score' => 6, 'team2_score' => 2]);
        }

        $url = route('backend.cavaliers-roster.candidates', [$event, $division]);
        $this->actingAs($this->administrator($interDistricts))->getJson($url)->assertForbidden();
        $this->actingAs($this->administrator($event));
        $this->getJson(route('backend.cavaliers-roster.candidates', [$event, $interDivision]))->assertNotFound();
        $this->getJson($url)->assertOk()
            ->assertJsonPath('candidates.0.id', $first->id)
            ->assertJsonPath('candidates.0.source', 'Inter-Districts')
            ->assertJsonPath('candidates.0.wins', null)
            ->assertJsonPath('candidates.1.id', $higher->id)
            ->assertJsonPath('candidates.1.source', 'Platteland')
            ->assertJsonPath('candidates.1.wins', 1)
            ->assertJsonPath('candidates.1.starting_credit', 1)
            ->assertJsonPath('candidates.3.id', $reserve->id)
            ->assertJsonPath('candidates.4.id', $lower->id)
            ->assertJsonCount(5, 'candidates');

        $draw->update(['published' => false]);
        $response = $this->getJson($url)->assertOk();
        $this->assertNull(collect($response->json('candidates'))->firstWhere('id', $higher->id)['wins']);
        $this->assertNull($platteland->fresh()->team_result_settings);
        $this->assertDatabaseCount('cavaliers_trial_roster_drafts', 0);
        $this->assertDatabaseCount('event_nominations', 0);
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

    private function division(?Event $event = null): array
    {
        $event ??= Event::factory()->create(['eventType' => 5]);
        $division = CategoryEvent::factory()->create([
            'event_id' => $event->id,
            'category_id' => Category::factory()->create(['name' => 'U10 Girls'])->id,
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
