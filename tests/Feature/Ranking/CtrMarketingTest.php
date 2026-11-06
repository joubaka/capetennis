<?php

namespace Tests\Feature\Ranking;

use App\Models\{Player, SiteSetting, User};
use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerRatingAccess};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Gate, View};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CtrMarketingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SiteSetting::set(PlayerRatingAccess::COMING_SOON_SETTING, '0');
    }

    public function test_guest_discovery_tracks_sales_and_purchase_details_remain_public(): void
    {
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldNotReceive('current');
        SiteSetting::set(PlayerRatingAccess::SETTING, '0');
        $this->get(route('home'))->assertOk()->assertDontSee('data-home-ctr', false);
        $this->get(route('ctr.index').'?member=1&ctr=1')->assertForbidden()->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('player-plus.terms'))->assertOk()->assertSee('Player Plus purchase details');
        SiteSetting::set(PlayerRatingAccess::SETTING, '1');
        $home = $this->get(route('home'))->assertOk()->assertSee('data-home-ctr', false)
            ->assertSee('Now open')->assertSee('Upgrade to Plus now!')
            ->assertSee('href="'.route('ctr.index').'"', false);
        $landing = $this->get(route('ctr.index'))->assertOk()->assertSee('Upgrade to Plus now!')->assertSee('R69.00')->assertSee('R699.00')
            ->assertDontSee('Your CTR access is active')->assertDontSee('Open CTR ratings')->assertDontSee('data-ctr-rating', false)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('player-plus.index'))->assertRedirect(route('login'));
        if (getenv('CT_CTR_MARKETING_QA_HTML') === '1') {
            file_put_contents(storage_path('framework/testing/ctr-marketing-guest-home.html'), $home->getContent());
            file_put_contents(storage_path('framework/testing/ctr-marketing-guest-intro.html'), $landing->getContent());
        }
    }

    public function test_coming_soon_marketing_is_visible_by_default_but_purchases_stay_closed(): void
    {
        SiteSetting::where('key', PlayerRatingAccess::COMING_SOON_SETTING)->delete();
        \Illuminate\Support\Facades\Cache::forget('site_setting.'.PlayerRatingAccess::COMING_SOON_SETTING);
        SiteSetting::set(PlayerRatingAccess::SETTING, '0');
        $this->get(route('home'))->assertOk()->assertSee('data-player-plus-coming-soon', false)
            ->assertSee('Explore CTR Ratings')->assertDontSee('Now open');
        $this->get(route('ctr.index'))->assertOk()->assertSee('Coming Soon')
            ->assertDontSee('R69.00')->assertDontSee('R699.00')
            ->assertDontSee('href="'.route('player-plus.index').'"', false);
        $this->actingAs(User::factory()->create());
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $this->get(route('player-plus.index'))->assertOk()->assertSee('New purchases are currently paused.');
        $this->post(route('player-plus.store', Player::factory()->create()), [
            'request_token' => (string) \Illuminate\Support\Str::uuid(), 'confirm' => '1',
            'plan' => 'annual_recurring', 'recurring_consent' => '1',
            'terms_version' => \App\Http\Controllers\Frontend\PlayerPlusController::TERMS_VERSION,
            'quoted_price_cents' => \App\Services\Performance\PlayerPlusService::priceCents(),
        ])->assertNotFound();
    }

    public function test_normal_user_discovery_tracks_sales_without_granting_premium_or_admin_access(): void
    {
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldNotReceive('current');
        $this->actingAs(User::factory()->create());
        // Entitlement does not control public offer discovery.
        Gate::define('player-premium-rating-discovery', fn () => true);
        SiteSetting::set(PlayerRatingAccess::SETTING, '0');
        $this->get(route('home'))->assertOk()->assertDontSee('data-home-ctr', false);
        $this->get(route('ctr.index'))->assertForbidden();
        SiteSetting::set(PlayerRatingAccess::SETTING, '1');
        $this->get(route('home'))->assertOk()->assertSee('data-home-ctr', false);
        $this->get(route('ctr.index'))->assertOk()->assertDontSee('Your CTR access is active')->assertDontSee('Open CTR ratings');
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $this->get(route('backend.player-performance.ratings'))->assertForbidden();
        $this->get(route('backend.player-performance.membership-preview.membership'))->assertForbidden();
        $this->get(route('my.tennis.rating', Player::factory()->create()))->assertForbidden();
    }

    public function test_both_navigation_layouts_follow_discovery_rule_for_guests_users_and_super_users(): void
    {
        foreach ([null, User::factory()->create(), $this->superUser()] as $user) {
            if ($user) $this->actingAs($user); else auth()->forgetGuards();
            foreach ([false, true] as $enabled) {
                SiteSetting::set(PlayerRatingAccess::SETTING, $enabled ? '1' : '0');
                $expected = $enabled || $user?->hasRole('super-user');
                foreach (['horizontalMenu', 'verticalMenu'] as $layout) {
                    $html = View::make('layouts.sections.menu.'.$layout, ['containerNav' => '', 'navbarFull' => true, 'showAdminHome' => false])->render();
                    if ($expected) $this->assertStringContainsString('>Player Plus</div>', $html);
                    else $this->assertStringNotContainsString('>Player Plus</div>', $html);
                }
            }
        }
    }

    private function superUser(): User
    {
        Role::findOrCreate('super-user', 'web');
        return User::factory()->create()->assignRole('super-user');
    }

    public function test_player_dashboard_coming_soon_hides_prices_and_links_to_marketing(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        SiteSetting::set(PlayerRatingAccess::SETTING, '0');
        SiteSetting::set(PlayerRatingAccess::COMING_SOON_SETTING, '1');
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $this->actingAs($user)->get(route('my.tennis', ['player' => $player->id]))
            ->assertOk()->assertSee(' data-player-plus-coming-soon>', false)
            ->assertSee('Pricing will be published when Player Plus launches.')
            ->assertDontSee('R69.00')->assertDontSee('R699.00')
            ->assertSee('href="'.route('ctr.index').'"', false)
            ->assertDontSee('href="'.route('player-plus.player', $player).'"', false);
    }

    public function test_super_users_keep_marketing_preview_when_sales_are_paused(): void
    {
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldNotReceive('current');
        $this->actingAs($this->superUser());
        foreach (['0', '1'] as $enabled) {
            SiteSetting::set(PlayerRatingAccess::SETTING, $enabled);
            $home = $this->get(route('home'))->assertOk()->assertSee('data-home-ctr', false)->assertSee(route('ctr.index'), false);
            $landing = $this->get(route('ctr.index'))->assertOk()->assertSee('Your CTR access is active')->assertSee('Open CTR ratings')
                ->assertSee(route('backend.player-performance.ratings'), false)->assertHeader('Cache-Control', 'no-store, private');
        }
        if (getenv('CT_CTR_MARKETING_QA_HTML') === '1') {
            file_put_contents(storage_path('framework/testing/ctr-marketing-home.html'), $home->getContent());
            file_put_contents(storage_path('framework/testing/ctr-marketing-intro.html'), $landing->getContent());
        }
    }
}
