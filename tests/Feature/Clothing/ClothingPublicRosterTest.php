<?php

namespace Tests\Feature\Clothing;

use App\Models\{CategoryEvent, ClothingItemType, ClothingOrder, ClothingOrderItem, ClothingSize, Event, EventType, NoProfileTeamPlayer, Player, Team, TeamPaymentOrder, TeamPlayer, TeamRegion, User};
use App\Services\EventPageDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClothingPublicRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_rosters_share_compact_layout_and_keep_ratings_privileged(): void
    {
        [$event, $team, $player, $region, $actor] = $this->scenario();
        $region->clothing_order = false;
        $this->actingAs($actor);

        foreach (['no-profile-team', 'profile-team'] as $partial) {
            $html = view('frontend.event.partials.'.$partial, compact('event', 'team', 'region'))->render();
            $this->assertStringContainsString('col-12 col-md-6 team-roster-column', $html);
            $this->assertStringContainsString('card-header border rounded-top', $html);
            $this->assertStringContainsString('flex-sm-row justify-content-between align-items-sm-center gap-2 team-player-row', $html);
            $this->assertStringContainsString('team-player-identity', $html);
            $this->assertStringNotContainsString('team-player-clothing-controls', $html);
            $this->assertStringNotContainsString('data-rating-player=', $html);
        }

        \Spatie\Permission\Models\Role::findOrCreate('super-user', 'web');
        $actor->assignRole('super-user');
        foreach (['no-profile-team', 'profile-team'] as $partial) {
            $html = view('frontend.event.partials.'.$partial, compact('event', 'team', 'region'))->render();
            $this->assertSame(1, substr_count($html, 'data-rating-player="'.$player->id.'"'));
            $this->assertStringContainsString('data-rating-field="'.$team->category->id.'"', $html);
        }

        $team->team_players_no_profile->first()->player_profile = null;
        $team->team_players_no_profile->first()->unsetRelation('profile');
        $html = view('frontend.event.partials.no-profile-team', compact('event', 'team', 'region'))->render();
        $this->assertStringNotContainsString('data-rating-player=', $html);
    }

    public function test_linked_imported_and_profile_rosters_use_the_same_canonical_clothing_context(): void
    {
        [$event, $team, $player, $region] = $this->scenario();
        foreach (['no-profile-team', 'profile-team'] as $partial) {
            $html = view('frontend.event.partials.'.$partial, compact('event', 'team', 'region'))->render();
            $this->assertStringContainsString('Order clothing', $html);
            foreach (['playerid' => $player->id, 'team' => $team->id, 'region' => $region->id, 'eventid' => $event->id] as $key => $id) $this->assertStringContainsString('data-'.$key.'="'.$id.'"', $html);
            $this->assertSame(1, substr_count($html, 'class="btn btn-sm btn-outline-secondary clothing-order"'));
        }
        $html = view('frontend.event.partials._region_team_picker', ['event' => $event, 'regions' => collect([$region])])->render();
        $this->assertStringContainsString('Clothing orders open', $html);
    }

    public function test_unlinked_imported_and_empty_rosters_do_not_render_an_order_context(): void
    {
        [$event, $team, , $region] = $this->scenario();
        $team->team_players_no_profile->first()->player_profile = null;
        $team->team_players_no_profile->first()->unsetRelation('profile');
        $html = view('frontend.event.partials.no-profile-team', compact('event', 'team', 'region'))->render();
        $this->assertStringNotContainsString('clothing-order"', $html);
        $this->assertStringContainsString('Link Profile', $html);
        $this->assertStringContainsString('Link a player profile before ordering clothing.', $html);
        $team->setRelation('team_players_no_profile', collect());
        $html = view('frontend.event.partials.no-profile-team', compact('event', 'team', 'region'))->render();
        $this->assertStringContainsString('No team slots defined', $html);
        $this->assertStringNotContainsString('clothing-order"', $html);
    }

    public function test_closed_opted_out_or_unusable_catalogue_and_unpublished_context_hide_clothing_actions(): void
    {
        [$event, $team, $player, $region] = $this->scenario();
        foreach ([['clothing_order' => 0], ['clothing_admin' => 0]] as $changes) {
            $region->fill($changes);
            $this->assertFalse($region->clothingOrdersAvailable());
            $html = view('frontend.event.partials.no-profile-team', compact('event', 'team', 'region'))->render();
            $this->assertStringNotContainsString('Order clothing', $html);
            $region->clothing_order = 1; $region->clothing_admin = 1;
        }
        $item = $region->clothingItems->first(); $item->price = 0;
        $this->assertFalse($region->clothingOrdersAvailable());
        $item->price = 100; $item->setRelation('sizes', collect());
        $this->assertFalse($region->clothingOrdersAvailable());
        $region->load('clothingItems.sizes'); $event->published = false;
        $html = view('frontend.event.partials.player-clothing-controls', ['event' => $event, 'team' => $team, 'player' => $player, 'playerName' => $player->full_name, 'region' => $region])->render();
        $this->assertStringNotContainsString('Order clothing', $html);
        $event->published = true; $team->published = false;
        $html = view('frontend.event.partials.no-profile-team', compact('event', 'team', 'region'))->render();
        $this->assertStringNotContainsString('Order clothing', $html);
    }

    public function test_paid_clothing_history_on_public_imported_roster_remains_payer_private(): void
    {
        [$event, $team, $player, $region, $actor] = $this->scenario();
        foreach ([[$actor, 'My shirt'], [User::factory()->create(), 'Other payer private garment']] as [$payer, $name]) {
            $order = ClothingOrder::create(['user_id' => $payer->id, 'event_id' => $event->id, 'team_id' => $team->id, 'player_id' => $player->id, 'pay_status' => 1, 'total' => 100, 'request_token' => (string) str()->uuid()]);
            ClothingOrderItem::create(['clothing_order_id' => $order->id, 'clothing_order_item_id' => $region->clothingItems->first()->id, 'clothing_item_size' => $region->clothingItems->first()->sizes->first()->id, 'item_name' => $name, 'size_name' => 'M', 'price' => 100, 'qty' => 2, 'line_total' => 200]);
        }
        $response = $this->actingAs($actor)->get(route('events.show', $event))->assertOk()->assertSee('My shirt')->assertSee('Qty 2')->assertDontSee('Other payer private garment');
        if (getenv('CT_CLOTHING_BROWSER_FIXTURE')) {
            $directory = storage_path('framework/testing/browserqa-clothing'); if (!is_dir($directory)) mkdir($directory, 0777, true);
            file_put_contents($directory.'/imported-roster.html', str_replace('http://localhost/', 'http://localhost/ct/public/', $response->getContent()));
            $team->update(['noProfile' => false]);
            $profileResponse = $this->get(route('events.show', $event))->assertOk();
            file_put_contents($directory.'/profile-roster.html', str_replace('http://localhost/', 'http://localhost/ct/public/', $profileResponse->getContent()));
        }
        auth()->logout();
        $this->get(route('events.show', $event))->assertOk()->assertDontSee('My shirt')->assertDontSee('Other payer private garment');
    }

    public function test_ordinary_registration_checkout_exposes_the_same_optional_clothing_modal(): void
    {
        [$event, $team, $player, , $actor] = $this->scenario(); $this->actingAs($actor);
        $order = new TeamPaymentOrder(['user_id' => $actor->id, 'event_id' => $event->id, 'team_id' => $team->id, 'player_id' => $player->id]); $order->id = 123;
        $html = view('frontend.payfast.team_payment', ['event' => $event, 'team' => $team, 'player' => $player, 'order' => $order,
            'payfast' => (object) ['item_name' => 'Test registration', 'custom_str4' => 'Test team'], 'total' => 100, 'regionFee' => 0,
            'walletBalance' => 100, 'walletReserved' => 100, 'payfastDue' => 0, 'autoSubmitPayfast' => false])->render();
        if (getenv('CT_CLOTHING_BROWSER_FIXTURE')) {
            $directory = storage_path('framework/testing/browserqa-clothing');
            if (!is_dir($directory)) mkdir($directory, 0777, true);
            file_put_contents($directory.'/checkout.html', str_replace('http://localhost/', 'http://localhost/ct/public/', $html));
        }
        $this->assertStringContainsString('Clothing (optional)', $html);
        $this->assertStringContainsString('Clothing has its own checkout and payment', $html);
        $this->assertStringContainsString('data-playerid="'.$player->id.'"', $html);
        $this->assertSame(1, substr_count($html, 'id="clothingOrderForm"'));
        $this->assertStringContainsString(route('clothingOrder.store'), $html);
        $this->assertDatabaseCount('clothing_orders', 0);
        $this->assertDatabaseCount('team_payment_orders', 0);
    }

    public function test_clothing_catalogue_displays_the_canonical_exact_customer_unit_price(): void
    {
        [$event, $team, $player, $region, $actor] = $this->scenario();
        $item = $region->clothingItems->first(); $item->update(['price' => 200, 'final_amount' => 275]);
        $catalogue = $this->actingAs($actor)->post(route('get.region.clothing.items'), ['region' => $region->id])->assertOk()
            ->assertSee('data-customer-price="275.00"', false)->assertSee('R275.00');
        if (getenv('CT_CLOTHING_BROWSER_FIXTURE')) {
            $directory = storage_path('framework/testing/browserqa-clothing'); if (!is_dir($directory)) mkdir($directory, 0777, true);
            file_put_contents($directory.'/catalogue.html', $catalogue->getContent());
        }
        $order = app(\App\Services\Clothing\ClothingOrderService::class)->create($actor, $event, $region, $team, $player,
            [$item->id => ['size' => $item->sizes->first()->id, 'qty' => 2]], (string) str()->uuid());
        $this->assertSame(550.0, (float) $order->total);
    }

    private function scenario(): array
    {
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Public clothing parity', 'type' => EventType::TEAM]);
        $event = Event::factory()->create(['eventType' => $type, 'published' => true, 'signUp' => true, 'start_date' => now()->addMonth(), 'deadline' => 2]);
        $region = TeamRegion::create(['region_name' => 'Clothing test region', 'clothing_order' => true, 'clothing_admin' => true]); $event->regions()->attach($region);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create(['name' => 'Clothing test team', 'category_event_id' => $category->id, 'region_id' => $region->id, 'noProfile' => true, 'published' => true, 'num_team_members' => 10]);
        $actor = User::factory()->create(); $player = Player::factory()->create();
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);
        NoProfileTeamPlayer::create(['team_id' => $team->id, 'player_profile' => $player->id, 'name' => $player->name, 'surname' => $player->surname, 'rank' => 1, 'pay_status' => 0]);
        $item = ClothingItemType::create(['region_id' => $region->id, 'item_type_name' => 'Shirt', 'price' => 100]);
        ClothingSize::create(['item_type' => $item->id, 'size' => 'M']);
        app(EventPageDataService::class)->prepareRegions($event);
        $region = $event->regions->first(); $team = $region->teams->first();
        return [$event, $team, $player, $region, $actor];
    }
}
