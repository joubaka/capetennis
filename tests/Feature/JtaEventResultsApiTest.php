<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryResult;
use App\Models\Event;
use App\Models\Player;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JtaEventResultsApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = User::factory()->create()
            ->createToken('jta-placement-test', ['jta-results:read'], now()->addHour())
            ->plainTextToken;
    }

    public function test_event_results_endpoint_requires_the_dedicated_token_ability(): void
    {
        $player = Player::factory()->create();
        $url = "/api/v1/integrations/jta/players/{$player->id}/event-results";

        $this->getJson($url)->assertUnauthorized();

        $wrongToken = User::factory()->create()->createToken('wrong-placement', ['read'])->plainTextToken;
        $this->withToken($wrongToken)->getJson($url)->assertForbidden();

        app('auth')->forgetGuards();
        $this->withToken($this->token)->getJson($url)->assertOk();
    }

    public function test_published_placement_only_event_is_exported_without_private_fields_or_invented_matches(): void
    {
        $player = Player::factory()->create([
            'name' => 'Placement',
            'surname' => 'Player',
            'email' => 'private-placement@example.test',
            'cellNr' => '0821234567',
            'dateOfBirth' => '2012-04-15',
        ]);
        $event = Event::factory()->create(['results_published' => true]);
        $category = Category::factory()->create(['name' => 'Boys U14']);
        $registration = $this->registrationFor([$player]);
        $result = $this->placement($event, $category, $registration, 3);

        $fieldRegistration = $this->registrationFor([Player::factory()->create()]);
        $this->placement($event, $category, $fieldRegistration, 1);

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/integrations/jta/players/{$player->id}/event-results")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.result_type', 'placement')
            ->assertJsonPath('data.0.source_result_id', "ct-placement-{$event->id}-{$category->id}-{$registration->id}")
            ->assertJsonPath('data.0.event.id', $event->id)
            ->assertJsonPath('data.0.category.name', 'Boys U14')
            ->assertJsonPath('data.0.position', 3)
            ->assertJsonPath('data.0.field_size', 2)
            ->assertJsonPath('data.0.placement_type', 'singles')
            ->assertJsonMissingPath('data.0.matches')
            ->assertJsonMissingPath('data.0.opponents')
            ->assertJsonMissingPath('data.0.sets');

        $this->assertStringNotContainsString('private-placement@example.test', $response->getContent());
        $this->assertStringNotContainsString('0821234567', $response->getContent());
        $this->assertStringNotContainsString('2012-04-15', $response->getContent());
        $this->assertSame($result->registration_id, $response->json('data.0.registration_id'));

        $this->withToken($this->token)
            ->getJson("/api/v1/integrations/jta/players/{$player->id}/results")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_unpublished_results_unpublished_events_and_unrelated_players_do_not_leak(): void
    {
        $player = Player::factory()->create();
        $other = Player::factory()->create();
        $category = Category::factory()->create();

        $resultsHidden = Event::factory()->create(['results_published' => false]);
        $this->placement($resultsHidden, $category, $this->registrationFor([$player]), 1);

        $eventHidden = Event::factory()->create(['published' => false, 'results_published' => true]);
        $this->placement($eventHidden, $category, $this->registrationFor([$player]), 2);

        $visibleOther = Event::factory()->create(['results_published' => true]);
        $this->placement($visibleOther, $category, $this->registrationFor([$other]), 3);

        $this->withToken($this->token)
            ->getJson("/api/v1/integrations/jta/players/{$player->id}/event-results")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_registration_based_doubles_placement_is_available_to_each_partner(): void
    {
        $partner1 = Player::factory()->create(['name' => 'First', 'surname' => 'Partner']);
        $partner2 = Player::factory()->create(['name' => 'Second', 'surname' => 'Partner']);
        $event = Event::factory()->create(['results_published' => true]);
        $category = Category::factory()->create();
        $registration = $this->registrationFor([$partner1, $partner2]);
        $this->placement($event, $category, $registration, 2);

        foreach ([$partner1, $partner2] as $player) {
            $this->withToken($this->token)
                ->getJson("/api/v1/integrations/jta/players/{$player->id}/event-results")
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.placement_type', 'doubles')
                ->assertJsonCount(2, 'data.0.players')
                ->assertJsonPath('data.0.registration_id', $registration->id);
        }
    }

    public function test_corrected_placement_keeps_source_id_changes_version_and_is_incrementally_exported(): void
    {
        $player = Player::factory()->create();
        $event = Event::factory()->create(['results_published' => true]);
        $category = Category::factory()->create();
        $registration = $this->registrationFor([$player]);
        $old = $this->placement($event, $category, $registration, 4);
        $url = "/api/v1/integrations/jta/players/{$player->id}/event-results";
        $before = $this->withToken($this->token)->getJson($url)->assertOk()->json('data.0');

        $old->delete();
        $this->placement($event, $category, $registration, 2);
        $since = urlencode(now()->subMinute()->toIso8601String());
        $after = $this->withToken($this->token)
            ->getJson($url.'?updated_since='.$since)
            ->assertOk()
            ->json('data.0');

        $this->assertSame($before['source_result_id'], $after['source_result_id']);
        $this->assertNotSame($before['source_version'], $after['source_version']);
        $this->assertSame(2, $after['position']);
    }

    public function test_latest_historical_duplicate_wins_and_page_and_cursor_pagination_are_unique(): void
    {
        $player = Player::factory()->create();
        $registration = $this->registrationFor([$player]);
        $event = Event::factory()->create(['results_published' => true]);
        $category = Category::factory()->create();
        $this->placement($event, $category, $registration, 5);
        $this->placement($event, $category, $registration, 4);

        foreach (range(1, 2) as $offset) {
            $nextEvent = Event::factory()->create(['results_published' => true]);
            $this->placement($nextEvent, Category::factory()->create(), $registration, $offset);
        }

        $base = "/api/v1/integrations/jta/players/{$player->id}/event-results?per_page=2";
        $page1 = $this->withToken($this->token)->getJson($base)->assertOk();
        $page2 = $this->withToken($this->token)->getJson($base.'&page=2')->assertOk();
        $ids = array_merge($page1->json('data.*.source_result_id'), $page2->json('data.*.source_result_id'));
        $this->assertCount(3, $ids);
        $this->assertCount(3, array_unique($ids));
        $this->assertSame(4, $page1->json('data.0.position'));

        $cursor1 = $this->withToken($this->token)->getJson($base.'&cursor=')->assertOk();
        $cursor2 = $this->withToken($this->token)->getJson($cursor1->json('links.next'))->assertOk();
        $cursorIds = array_merge($cursor1->json('data.*.source_result_id'), $cursor2->json('data.*.source_result_id'));
        $this->assertCount(3, array_unique($cursorIds));
    }

    private function registrationFor(array $players): Registration
    {
        $registration = Registration::factory()->create();
        $registration->players()->attach(collect($players)->pluck('id'));

        return $registration;
    }

    private function placement(Event $event, Category $category, Registration $registration, int $position): CategoryResult
    {
        return CategoryResult::create([
            'event_id' => $event->id,
            'category_id' => $category->id,
            'registration_id' => $registration->id,
            'position' => $position,
        ]);
    }
}
