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

    public function test_guest_discovery_tracks_sales_and_purchase_details_remain_public(): void
    {
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldNotReceive('current');
        SiteSetting::set(PlayerRatingAccess::SETTING, '0');
        $this->get(route('home'))->assertOk()->assertDontSee('data-home-ctr', false);
        $this->get(route('ctr.index').'?member=1&ctr=1')->assertForbidden()->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('player-plus.terms'))->assertOk()->assertSee('Player Plus purchase details');
        SiteSetting::set(PlayerRatingAccess::SETTING, '1');
        $home = $this->get(route('home'))->assertOk()->assertSee('data-home-ctr', false);
        $landing = $this->get(route('ctr.index'))->assertOk()->assertSee('Explore Player Plus')->assertSee('R69.00')->assertSee('R699.00')
            ->assertDontSee('Your CTR access is active')->assertDontSee('Open CTR ratings')->assertDontSee('data-ctr-rating', false)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('player-plus.index'))->assertRedirect(route('login'));
        if (getenv('CT_CTR_MARKETING_QA_HTML') === '1') {
            file_put_contents(storage_path('framework/testing/ctr-marketing-guest-home.html'), $home->getContent());
            file_put_contents(storage_path('framework/testing/ctr-marketing-guest-intro.html'), $landing->getContent());
        }
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
