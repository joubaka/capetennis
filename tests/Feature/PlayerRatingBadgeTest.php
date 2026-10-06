<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, CategoryEventRegistration, CategoryResult, Draw, Event, Fixture, Player, Registration, TeamFixture, TeamFixturePlayer, User};
use App\Services\Performance\{PlayerRatingBadgeService, PlayerSharedAbilityService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerRatingBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): void
    {
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
    }

    private function snapshot(int $id = 1): array
    {
        return [$id => [
            ['score' => 54.8, 'cohort' => 'u10 boys', 'component' => 'u10-connected', 'last_played' => '2026-09-01', 'confidence_index' => 42, 'confidence_band' => 'Moderate', 'confidence_as_of' => '2026-10-06', 'last_eligible_activity' => '2026-09-01'],
            ['score' => 72.3, 'cohort' => 'u12 boys', 'component' => 'u12-separate', 'last_played' => '2026-10-01', 'confidence_index' => 75, 'confidence_band' => 'Stronger', 'confidence_as_of' => '2026-10-06', 'last_eligible_activity' => '2026-10-01'],
        ]];
    }

    private function draw(string $label = 'U/10 Boys A', ?CategoryEvent $field = null): Draw
    {
        $event = $field?->event ?? Event::factory()->create();
        $field ??= CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => $label])->id]);
        return Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $field->id, 'drawName' => $label]);
    }

    public function test_guest_and_non_super_admin_have_no_badge_assets_html_json_or_lookup(): void
    {
        $this->partialMock(PlayerSharedAbilityService::class)->shouldNotReceive('badgeSnapshot');
        $url = route('backend.player-performance.badges');
        $this->getJson($url.'?players[]=1')->assertUnauthorized();
        foreach ([null, 'admin', 'convenor'] as $role) {
            if ($role) { Role::findOrCreate($role, 'web'); }
            $user = User::factory()->create();
            if ($role) { $user->assignRole($role); }
            $this->actingAs($user);
            $this->getJson($url.'?players[]=1')->assertForbidden();
            $html = Blade::render('Name<x-player-rating :player-id="1" />');
            $this->assertStringNotContainsString('player-rating-badge', $html);
            $this->assertStringNotContainsString('54.8', $html);
            $this->assertStringNotContainsString('Provisional singles ability', $html);
            $this->assertSame('', trim(view('draw.partials.player-rating-assets')->render()));
            $this->assertNull(app(PlayerRatingBadgeService::class)->forPlayer(1));
        }
        $this->assertSame([], (new PlayerSharedAbilityService)->badgeSnapshot());
    }

    public function test_private_badge_is_exact_contextual_and_snapshot_is_loaded_once(): void
    {
        $this->superAdmin();
        $this->partialMock(PlayerSharedAbilityService::class)->shouldReceive('badgeSnapshot')->once()->andReturn($this->snapshot());
        $draw = $this->draw();
        $service = app(PlayerRatingBadgeService::class);
        $this->assertSame('54.8', $service->forPlayer(1, $draw)['label']);
        $this->assertSame('72.3', $service->forPlayer(1)['label']);
        $this->assertNull($service->forPlayer(2, $draw));
        $this->assertNull($service->forPlayer(1, $this->draw('U10 Girls')));
        $this->assertNull($service->forPlayer(1, $this->draw('Green ball')));
        $this->assertNull($service->forPlayer(1, $this->draw('U10 Boys Doubles')));
        $this->assertNull($service->forPlayer(1, $this->draw('U10 Boys A B')));
        $this->assertSame('54.8', $service->forPlayer(1, $draw->categoryEvent->category)['label']);
        $html = Blade::render('Name<x-player-rating :player-id="1" :context="$draw" />', ['draw' => $draw]);
        $this->assertStringContainsString('54.8', $html);
        $this->assertStringNotContainsString('72.3', $html);
        $this->assertStringContainsString('u10-connected', $html);
        $this->assertStringContainsString('Only compare players in this cohort and group', $html);
    }

    public function test_legacy_and_cross_event_category_reference_use_actual_draw_context(): void
    {
        $this->superAdmin();
        $this->partialMock(PlayerSharedAbilityService::class)->shouldReceive('badgeSnapshot')->once()->andReturn($this->snapshot());
        $foreign = $this->draw('U12 Boys');
        $draw = $this->draw();
        $draw->category_event_id = $foreign->category_event_id;
        $draw->unsetRelation('categoryEvent');
        $this->assertSame('54.8', app(PlayerRatingBadgeService::class)->forPlayer(1, $draw)['label']);
        $legacy = $this->draw('U10B Boys');
        $legacy->category_event_id = null;
        $legacy->setRelation('categoryEvent', null);
        $this->assertSame('54.8', app(PlayerRatingBadgeService::class)->forPlayer(1, $legacy)['label']);
    }

    public function test_batched_endpoint_is_bounded_read_only_and_no_store(): void
    {
        $this->superAdmin();
        $player = Player::factory()->create();
        $registration = Registration::factory()->create();
        $registration->players()->attach($player);
        $draw = $this->draw();
        $this->partialMock(PlayerSharedAbilityService::class)->shouldReceive('badgeSnapshot')->once()->andReturn($this->snapshot($player->id));
        $url = route('backend.player-performance.badges');
        $this->getJson($url.'?players[]='.$player->id.'&registrations[]='.$registration->id.'&draw_id='.$draw->id)
            ->assertOk()->assertJsonPath('ratings.p:'.$player->id.'.0.label', '54.8')
            ->assertJsonPath('ratings.r:'.$registration->id.'.0.label', '54.8')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(1, Player::count());
        $this->assertSame(1, Registration::count());
        $this->postJson($url)->assertStatus(405);
        $this->getJson($url.'?'.http_build_query(['players' => range(1, 101)]))->assertUnprocessable();
        $this->getJson($url.'?draw_id='.$draw->id.'&category_event_id='.$draw->category_event_id)->assertUnprocessable();
        $this->getJson($url.'?players[]=-1')->assertUnprocessable();
    }

    public function test_fixture_and_svg_render_actual_names_with_badges_only_for_super_admin(): void
    {
        $draw = $this->draw();
        $player = Player::factory()->create(['name' => 'Long test player name', 'surname' => 'Surname']);
        $registration = Registration::factory()->create();
        $registration->players()->attach($player);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $registration->id]);
        $data = ['draw' => $draw, 'event' => $draw->event, 'fixtures' => collect([$fixture])];
        $this->partialMock(PlayerSharedAbilityService::class)->shouldReceive('badgeSnapshot')->once()->andReturn($this->snapshot($player->id));
        $guest = view('frontend.fixture.fixture-table', $data)->render();
        $this->assertStringNotContainsString('player-rating-badge', $guest);
        $this->superAdmin();
        $html = view('frontend.fixture.fixture-table', $data)->render();
        $this->assertStringContainsString('54.8', $html);
        $this->assertStringNotContainsString('72.3', $html);
        $svg = view('draw.partials.svg-player-identity', ['draw' => $draw, 'registration' => $registration, 'x' => 10, 'y' => 20, 'maxWidth' => 135])->render();
        $this->assertStringContainsString('<tspan class="player-rating-badge"', $svg);
        $this->assertStringContainsString('[54.8 | C42]', $svg);
        $this->assertStringContainsString('<title>'.$player->full_name.'</title>', $svg);
        $this->assertStringContainsString('...', $svg);
    }

    public function test_published_lineup_order_of_play_partial_preserves_identity_and_imported_names(): void
    {
        $this->superAdmin();
        $draw = $this->draw();
        $this->partialMock(PlayerSharedAbilityService::class)->shouldReceive('badgeSnapshot')->once()->andReturn($this->snapshot());
        $lineup = ['players' => [['name' => 'Rated player', 'rank' => 1, 'player_id' => 1], ['name' => 'Imported player', 'rank' => 2, 'player_id' => null]], 'region' => 'OB'];
        $html = view('frontend.fixture.lineup-side', ['lineup' => $lineup, 'ratingContext' => $draw])->render();
        $this->assertSame(1, substr_count($html, 'class="badge player-rating-badge'));
        $this->assertStringContainsString('54.8', $html);
        $this->assertStringContainsString('Imported player', $html);
        $this->assertStringContainsString('Team roster rank', $html);
    }

    public function test_private_estimate_from_real_published_results_disappears_after_unpublication(): void
    {
        $this->superAdmin();
        $draw = $this->draw();
        $event = $draw->event;
        $event->update(['published' => true, 'results_published' => true, 'start_date' => '2026-09-01', 'end_date' => '2026-09-02']);
        $playerId = null;
        foreach ([1, 2] as $position) {
            $player = Player::factory()->create();
            $registration = Registration::factory()->create();
            $registration->players()->attach($player);
            CategoryEventRegistration::factory()->create(['category_event_id' => $draw->category_event_id, 'registration_id' => $registration->id]);
            CategoryResult::create(['event_id' => $event->id, 'category_id' => $draw->categoryEvent->category_id, 'registration_id' => $registration->id, 'position' => $position]);
            $playerId ??= $player->id;
        }
        $url = route('backend.player-performance.badges').'?players[]='.$playerId.'&draw_id='.$draw->id;
        $response = $this->getJson($url)->assertOk();
        $this->assertGreaterThan(50, $response->json('ratings.p:'.$playerId.'.0.score'));
        \Illuminate\Support\Facades\DB::table('events')->where('id', $event->id)->update(['results_published' => false]);
        app()->forgetInstance(PlayerRatingBadgeService::class);
        $this->getJson($url)->assertOk()->assertJsonPath('ratings.p:'.$playerId, []);
    }

    public function test_shareable_exports_have_no_private_html_svg_or_assets_even_for_super_admin(): void
    {
        $this->superAdmin();
        $this->partialMock(PlayerSharedAbilityService::class)->shouldNotReceive('badgeSnapshot');
        foreach (['event.draw.get.pdf', 'headoffice.drawPack', 'headoffice.printDrawsPdf', 'headoffice.printDrawsData'] as $name) {
            $route = new \Illuminate\Routing\Route(['GET'], 'test-export', fn () => null);
            $route->name($name);
            request()->setRouteResolver(fn () => $route);
            $this->assertNull(app(PlayerRatingBadgeService::class)->forPlayer(1));
            $this->assertSame('', trim(Blade::render('<x-player-rating :player-id="1" />')));
            $this->assertSame('', trim(Blade::render('<x-player-rating :player-id="1" :svg="true" />')));
            $this->assertSame('', trim(view('draw.partials.player-rating-assets')->render()));
        }
    }

    public function test_event_entry_badge_keeps_change_category_attribute_plain_and_escaped(): void
    {
        $this->superAdmin();
        $draw = $this->draw();
        $event = $draw->event;
        $player = Player::factory()->create(['name' => 'Test "Quoted"', 'surname' => 'Player']);
        $registration = Registration::factory()->create();
        $registration->players()->attach($player);
        CategoryEventRegistration::factory()->create(['category_event_id' => $draw->category_event_id, 'registration_id' => $registration->id, 'payment_status_id' => 1, 'user_id' => auth()->id(), 'status' => 'active']);
        $this->partialMock(PlayerSharedAbilityService::class)->shouldReceive('badgeSnapshot')->once()->andReturn($this->snapshot($player->id));
        $html = view('frontend.event.eventTypes.individual', ['event' => $event, 'eventCats' => collect([$draw->categoryEvent->fresh(['categoryEventRegistrations.registration.players', 'category'])]),
            'canWithdraw' => true, 'sDate' => null, 'eDate' => null, 'formatEntryLine' => null, 'formatWithdrawalLine' => null])->render();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $button = $xpath->query('//button[contains(@class, "move-category-btn")]')->item(0);
        $this->assertNotNull($button);
        $this->assertSame($player->full_name, $button->getAttribute('data-player'));
        $this->assertStringContainsString('54.8', $html);
        $this->assertStringNotContainsString('player-rating', $button->getAttribute('data-player'));
    }

    public function test_fixture_side_lookup_uses_real_registration_and_draw_and_rejects_wrong_draw(): void
    {
        $this->superAdmin();
        $draw = $this->draw();
        $player = Player::factory()->create();
        $registration = Registration::factory()->create();
        $registration->players()->attach($player);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $registration->id]);
        $this->partialMock(PlayerSharedAbilityService::class)->shouldReceive('badgeSnapshot')->once()->andReturn($this->snapshot($player->id));
        $url = route('backend.player-performance.badges').'?fixtures[]='.$fixture->id;
        $this->getJson($url)->assertOk()->assertJsonPath('ratings.f:'.$fixture->id.':1.0.label', '54.8')->assertJsonPath('ratings.f:'.$fixture->id.':2', []);
        $other = $this->draw('U12 Boys');
        $this->getJson($url.'&draw_id='.$other->id)->assertOk()->assertJsonPath('ratings.f:'.$fixture->id.':1', []);
        $this->getJson(route('backend.player-performance.badges').'?'.http_build_query(['fixtures' => range(1, 101)]))->assertUnprocessable();
        $html = view('components.scheduled-participants', ['row' => ['participants' => ['Known home', 'Pending away'], 'fixture_kind' => 'individual', 'fixture_id' => $fixture->id, 'draw_id' => $draw->id]])->render();
        $this->assertStringContainsString('data-rating-fixture="'.$fixture->id.'"', $html);
        $this->assertStringContainsString('data-rating-side="1"', $html);
        $this->assertStringContainsString('data-rating-side="2"', $html);
    }

    public function test_canonical_results_page_has_private_contextual_badges_and_guest_names_only(): void
    {
        $draw = $this->draw();
        $player = Player::factory()->create();
        $registration = Registration::factory()->create();
        $registration->players()->attach($player);
        $result = CategoryResult::create(['event_id' => $draw->event_id, 'category_id' => $draw->categoryEvent->category_id, 'registration_id' => $registration->id, 'position' => 1]);
        $data = ['event' => $draw->event, 'categories' => collect([$draw->categoryEvent]), 'categoryResults' => collect([$draw->categoryEvent->category_id => collect([$result])])];
        $guest = view('frontend.event.results.show', $data)->render();
        $this->assertStringContainsString($player->full_name, $guest);
        $this->assertStringNotContainsString('player-rating-badge', $guest);
        $this->assertStringNotContainsString('CTPlayerRatingConfig', $guest);
        $this->superAdmin();
        $this->partialMock(PlayerSharedAbilityService::class)->shouldReceive('badgeSnapshot')->once()->andReturn($this->snapshot($player->id));
        $html = view('frontend.event.results.show', $data)->render();
        $this->assertStringContainsString('54.8', $html);
        $this->assertStringNotContainsString('72.3', $html);
        $this->assertStringContainsString('CTPlayerRatingConfig', $html);
    }

    public function test_team_presenter_keeps_public_payload_shape_and_adds_profile_identity_only_for_super_admin(): void
    {
        $draw = $this->draw();
        $player = Player::factory()->create();
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'round_nr' => 1,
            'match_nr' => 1, 'match_status' => 0, 'numSets' => 3, 'home_rank_nr' => 1]);
        TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 1, 'team1_id' => $player->id]);
        $fixture = $fixture->fresh();
        $presenter = app(\App\Services\TeamFixtureLineupPresenter::class);
        $presenter->prepare(collect([$fixture]));
        $this->assertSame(['name', 'rank'], array_keys($fixture->lineup_display['home']['players'][0]));
        $this->superAdmin();
        $presenter->prepare(collect([$fixture]));
        $this->assertSame($player->id, $fixture->lineup_display['home']['players'][0]['player_id']);
        $this->assertArrayNotHasKey('score', $fixture->lineup_display['home']['players'][0]);
    }
    public function test_confidence_index_zero_is_visible_in_private_html_svg_and_dynamic_payload(): void
    {
        $this->superAdmin(); $snapshot = $this->snapshot();
        foreach ($snapshot[1] as &$rating) { $rating['confidence_index'] = 0; $rating['confidence_band'] = 'Very low'; }
        unset($rating);
        $this->partialMock(PlayerSharedAbilityService::class)->shouldReceive('badgeSnapshot')->once()->andReturn($snapshot);
        $html = Blade::render('Name<x-player-rating :player-id="1" />');
        $this->assertStringContainsString('72.3 | C0', $html);
        $this->assertStringContainsString('Very low', $html);
        $this->assertStringContainsString('not an accuracy percentage', $html);
        $svg = Blade::render('<svg><text>Name<x-player-rating :player-id="1" :svg="true" /></text></svg>');
        $this->assertStringContainsString('[72.3 | C0]', $svg);
        $this->getJson(route('backend.player-performance.badges').'?players[]=1')->assertOk()
            ->assertJsonPath('ratings.p:1.0.confidence_index', 0)
            ->assertJsonPath('ratings.p:1.0.display_label', '72.3 | C0');
    }

}
