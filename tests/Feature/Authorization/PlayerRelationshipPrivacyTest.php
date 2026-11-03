<?php

namespace Tests\Feature\Authorization;

use App\Models\{Player, PlayerPlusAccessOverride, SiteSetting, User};
use App\Services\Performance\{PlayerPlusReadiness, PlayerRatingAccess};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerRelationshipPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
    }

    public function test_unrelated_accounts_cannot_open_either_private_profile_or_global_contact_table(): void
    {
        $owner = User::factory()->create(); $stranger = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id, 'email' => 'private-family@example.test', 'cellNr' => '0829999999']);
        foreach (['backend.player.profile', 'frontend.player.profile', 'player.results', 'player.events.results'] as $route) {
            $this->actingAs($stranger)->getJson(route($route, $player->id))->assertForbidden()->assertDontSee($player->email)->assertDontSee('0829999999');
        }
        $this->actingAs($stranger)->getJson(route('player.data'))->assertForbidden();
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_existing_pivot_and_legacy_relationships_can_read_profiles_without_plus(): void
    {
        $user = User::factory()->create(); $legacy = Player::factory()->create(['userId' => $user->id]);
        $pivot = Player::factory()->create(); $user->players()->attach($pivot);
        foreach ([$legacy, $pivot] as $player) {
            $this->actingAs($user)->get(route('backend.player.profile', $player->id))->assertOk()->assertSee($player->surname);
            $this->actingAs($user)->get(route('frontend.player.profile', $player->id))->assertOk()->assertSee($player->surname);
            $this->actingAs($user)->get(route('player.profile.edit', $player))->assertOk();
        }
    }

    public function test_event_convenor_is_not_global_private_profile_staff_but_admin_remains_authorized(): void
    {
        $player = Player::factory()->create();
        Role::findOrCreate('convenor', 'web'); Role::findOrCreate('admin', 'web');
        $convenor = User::factory()->create()->assignRole('convenor');
        $this->actingAs($convenor)->getJson(route('backend.player.profile', $player->id))->assertForbidden();
        $this->actingAs($convenor)->getJson(route('player.data'))->assertForbidden();
        $admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($admin)->get(route('backend.player.profile', $player->id))->assertOk();
        $this->actingAs($admin)->getJson(route('player.data'))->assertOk();
    }

    public function test_sponsor_offer_redacts_admin_access_details_and_private_navigation(): void
    {
        SiteSetting::set(PlayerRatingAccess::SETTING, '1');
        $owner = User::factory()->create(); $sponsor = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        PlayerPlusAccessOverride::create(['player_id' => $player->id, 'mode' => 'grant', 'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(17), 'version' => 1]);
        $readiness = Mockery::mock(PlayerPlusReadiness::class); $readiness->shouldReceive('forPlayer')->andReturn(null);
        $this->app->instance(PlayerPlusReadiness::class, $readiness);
        $response = $this->actingAs($sponsor)->get(route('player-plus.player', $player))->assertOk()
            ->assertSee('A new purchase for this player is not currently available')
            ->assertDontSee('admin-granted')->assertDontSee('Private support case detail')
            ->assertDontSee(route('my.tennis.record', $player), false);
        $this->assertNull($response->viewData('adminAccess')); $this->assertNull($response->viewData('membership'));
        $this->assertTrue($response->viewData('purchaseBlocked'));
        $this->assertFalse($response->viewData('canViewRating'));
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->actingAs($owner)->get(route('player-plus.player', $player))->assertOk()->assertSee('admin-granted')
            ->assertSee(route('my.tennis.record', $player), false)->assertSee(route('my.tennis.event-history', $player), false);
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_duplicate_new_profile_attempt_does_not_return_existing_private_contacts(): void
    {
        $owner = User::factory()->create(); $claimant = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id, 'name' => 'Example', 'surname' => 'Identity', 'dateOfBirth' => '2012-04-03', 'email' => 'private-owner@example.test']);
        $this->actingAs($claimant)->postJson(route('player.store'), ['player_name' => 'Example', 'player_surname' => 'Identity',
            'dob' => '2012-04-03', 'gender' => 1, 'cell_nr' => '0821111111'])
            ->assertUnprocessable()->assertDontSee('private-owner@example.test');
        $this->assertDatabaseCount('players', 1); $this->assertDatabaseCount('user_players', 0);
        $this->assertSame($owner->id, $player->fresh()->userId);
    }

    public function test_sponsor_search_is_name_only_and_does_not_reveal_contact_hints(): void
    {
        $user = User::factory()->create();
        Player::factory()->create(['name' => 'Example', 'surname' => 'Lookup', 'email' => 'hidden-contact@example.test']);
        $this->actingAs($user)->getJson(route('player.search', ['q' => 'Example', 'format' => 'select2']))->assertOk()
            ->assertJsonCount(1, 'results')->assertDontSee('hidden-contact')->assertJsonMissingPath('results.0.masked_email');
        $this->actingAs($user)->getJson(route('player.search', ['q' => 'hidden-contact@example.test']))->assertExactJson([]);
    }
}
