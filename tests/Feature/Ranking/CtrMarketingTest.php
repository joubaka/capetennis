<?php

namespace Tests\Feature\Ranking;

use App\Models\{SiteSetting, User};
use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerRatingAccess};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CtrMarketingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_see_the_home_ctr_link_or_open_the_introduction(): void
    {
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldNotReceive('current');
        $this->get(route('home'))->assertOk()->assertDontSee('data-home-ctr', false)
            ->assertDontSee(route('ctr.index'), false);
        $this->get(route('ctr.index').'?member=1&ctr=1')->assertForbidden()
            ->assertDontSee('Discover CTR Rankings')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_nonsuper_users_cannot_see_ctr_marketing_even_with_a_future_discovery_entitlement(): void
    {
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldNotReceive('current');
        $this->actingAs(User::factory()->create());
        foreach ([false, true] as $futureEntitlement) {
            if ($futureEntitlement) {
                SiteSetting::set(PlayerRatingAccess::SETTING, '1');
                Gate::define('player-premium-rating-discovery', fn () => true);
            }
            $this->get(route('home'))->assertOk()->assertDontSee('data-home-ctr', false)
                ->assertDontSee(route('ctr.index'), false);
            $this->get(route('ctr.index').'?member=1&ctr=1')->assertForbidden()
                ->assertDontSee('Your CTR access is active')->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public function test_super_users_can_open_the_existing_protected_ctr_directory(): void
    {
        Role::findOrCreate('super-user', 'web');
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldNotReceive('current');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $home = $this->get(route('home'))->assertOk()->assertSee('data-home-ctr', false)
            ->assertSee(route('ctr.index'), false);
        $landing = $this->get(route('ctr.index'))->assertOk()->assertSee('Your CTR access is active')
            ->assertSee('Open CTR ratings')->assertSee(route('backend.player-performance.ratings'), false)
            ->assertHeader('Cache-Control', 'no-store, private');
        if (getenv('CT_CTR_MARKETING_QA_HTML') === '1') {
            file_put_contents(storage_path('framework/testing/ctr-marketing-home.html'), $home->getContent());
            file_put_contents(storage_path('framework/testing/ctr-marketing-intro.html'), $landing->getContent());
        }
    }
}
