<?php

namespace Tests\Feature\Ranking;

use App\Services\Performance\{PlayerPlusService, PlayerRatingAccess};
use Illuminate\Support\Facades\{Blade, Cache, Schema};
use Tests\TestCase;

class CtrLandingPresentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Schema::shouldReceive('hasTable')->andReturn(true);
        foreach ([PlayerPlusService::PRICE_SETTING => '69900', PlayerPlusService::MONTHLY_PRICE_SETTING => '6900',
            PlayerPlusService::MONTHLY_ENABLED_SETTING => '0', PlayerRatingAccess::SETTING => '1'] as $key => $value) {
            Cache::put('site_setting.'.$key, (object) ['value' => $value]);
        }
    }

    public function test_landing_explains_both_plans_without_exposing_administrative_links(): void
    {
        $source = file_get_contents(resource_path('views/frontend/ctr.blade.php'));
        preg_match("/@section\('page-style'\)(.*?)@endsection/s", $source, $style);
        preg_match("/@section\('content'\)(.*)@endsection/s", $source, $content);
        // Compile the actual page sections; the shared site shell is excluded from this isolated fixture.
        $html = Blade::render($style[1].$content[1], ['isSuperUser' => false]);
        foreach (['R69.00', 'R699.00', 'Both plans renew automatically', 'Monthly purchases are currently unavailable.',
            'No actual player data', 'Upgrade to Plus now!', 'Purchase details', 'Private history requires a linked account'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('Open CTR ratings', $html);
        $this->assertStringNotContainsString('data-player-plus-benefits', $html);
        if (getenv('CT_CTR_LANDING_QA_HTML') === '1') {
            file_put_contents(storage_path('framework/testing/ctr-landing-presentation.html'), '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="http://localhost/assets/vendor/css/rtl/core.css"><link rel="stylesheet" href="http://localhost/assets/vendor/css/rtl/theme-default.css"><link rel="stylesheet" href="http://localhost/assets/vendor/fonts/tabler-icons.css"></head><body style="padding:16px;background:#f5f6f8">'.$html.'</body></html>');
        }
        $html = Blade::render($style[1].$content[1], ['isSuperUser' => true]);
        $this->assertStringContainsString('Open CTR ratings', $html);
        $this->assertStringContainsString(route('backend.player-performance.ratings'), $html);
    }
}
