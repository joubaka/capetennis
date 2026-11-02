<?php

namespace Tests\Feature;

use App\Services\Performance\{PlayerPlusService, PlayerRatingAccess};
use Illuminate\Support\Facades\{Blade, Cache, Schema};
use Tests\TestCase;

class PlayerPlusPricingDisplayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Schema::shouldReceive('hasTable')->andReturn(true);
        foreach ([PlayerPlusService::PRICE_SETTING => 'invalid', PlayerPlusService::MONTHLY_PRICE_SETTING => 'invalid',
            PlayerPlusService::MONTHLY_ENABLED_SETTING => '0', PlayerRatingAccess::SETTING => '1'] as $key => $value) {
            Cache::put('site_setting.'.$key, (object) ['value' => $value]);
        }
    }

    public function test_offers_show_both_default_prices_and_disabled_monthly_status(): void
    {
        $html = Blade::render('<x-player-plus-benefits />');
        $this->assertStringContainsString('R69.00', $html);
        $this->assertStringContainsString('R699.00', $html);
        $this->assertStringContainsString('per player / month', $html);
        $this->assertStringContainsString('per player / year', $html);
        $this->assertStringContainsString('Both plans renew automatically through PayFast until cancelled', $html);
        $this->assertStringContainsString('Monthly purchases are currently unavailable.', $html);
    }

    public function test_paused_sales_do_not_hide_either_price_or_offer_checkout(): void
    {
        Cache::put('site_setting.'.PlayerRatingAccess::SETTING, (object) ['value' => '0']);
        $html = Blade::render('<x-player-plus-pricing />');
        $this->assertStringContainsString('R69.00', $html);
        $this->assertStringContainsString('R699.00', $html);
        $this->assertStringContainsString('New purchases are currently paused.', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    public function test_historical_purchase_keeps_its_saved_price_and_once_off_terms(): void
    {
        $html = Blade::render('<x-player-plus-benefits :price-cents="49900" plan="annual" />');
        $this->assertStringContainsString('R499.00', $html);
        $this->assertStringContainsString('no automatic renewal', $html);
        $this->assertStringNotContainsString('R699.00', $html);
        $this->assertStringNotContainsString('R69.00', $html);
    }

    public function test_offers_use_current_settings_without_repricing_existing_orders(): void
    {
        Cache::put('site_setting.'.PlayerPlusService::PRICE_SETTING, (object) ['value' => '79995']);
        Cache::put('site_setting.'.PlayerPlusService::MONTHLY_ENABLED_SETTING, (object) ['value' => '1']);
        $html = Blade::render('<x-player-plus-pricing />');
        $this->assertStringContainsString('R799.95', $html);
        $this->assertStringNotContainsString('currently unavailable', $html);
        $html = Blade::render('<x-player-plus-benefits :price-cents="6900" plan="monthly" />');
        $this->assertStringContainsString('R69.00', $html);
        $this->assertStringContainsString('Monthly access', $html);
        $this->assertStringNotContainsString('R799.95', $html);
    }
}
