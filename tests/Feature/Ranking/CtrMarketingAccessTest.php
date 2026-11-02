<?php

namespace Tests\Feature\Ranking;

use App\Http\Controllers\Frontend\CtrController;
use App\Models\User;
use App\Services\Performance\{PlayerPlusService, PlayerRatingAccess};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Blade, Cache, Schema};
use Mockery;
use Tests\TestCase;

class CtrMarketingAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Schema::shouldReceive('hasTable')->andReturn(true);
        foreach ([PlayerPlusService::PRICE_SETTING => '69900', PlayerPlusService::MONTHLY_PRICE_SETTING => '6900',
            PlayerPlusService::MONTHLY_ENABLED_SETTING => '0'] as $key => $value) {
            Cache::put('site_setting.'.$key, (object) ['value' => $value]);
        }
    }

    public function test_sales_switch_and_super_preview_control_marketing_without_entitlements(): void
    {
        foreach ([false, true] as $enabled) {
            Cache::put('site_setting.'.PlayerRatingAccess::SETTING, (object) ['value' => $enabled ? '1' : '0']);
            foreach ([null, false, true] as $super) {
                $user = $super === null ? null : Mockery::mock(User::class)->makePartial();
                $user?->shouldReceive('hasRole')->with('super-user')->andReturn($super);
                $expected = $enabled || $super === true;
                $access = app(PlayerRatingAccess::class);
                $this->assertSame($expected, $access->allowsMarketing($user));
                $request = Request::create('/ctr');
                $request->setUserResolver(fn () => $user);
                $response = app(CtrController::class)($request);
                $this->assertSame($expected ? 200 : 403, $response->getStatusCode());
                if ($expected) $this->assertSame($super === true, $response->getOriginalContent()->getData()['isSuperUser']);
                $html = Blade::render('@if(app(\\App\\Services\\Performance\\PlayerRatingAccess::class)->allowsMarketing($user))<x-player-plus-pricing />@endif', ['user' => $user]);
                if ($expected) {
                    $this->assertStringContainsString('R69.00', $html);
                    $this->assertStringContainsString('R699.00', $html);
                } else $this->assertSame('', $html);
            }
        }
    }

    public function test_public_purchase_details_keep_checkout_and_order_routes_authenticated(): void
    {
        $routes = app('router')->getRoutes();
        $this->assertNotContains('auth', $routes->getByName('player-plus.terms')->gatherMiddleware());
        foreach (['index', 'player', 'store', 'orders.index', 'orders.show', 'orders.pay', 'orders.stop-renewal'] as $name) {
            $this->assertContains('auth', $routes->getByName('player-plus.'.$name)->gatherMiddleware());
        }
    }
}
