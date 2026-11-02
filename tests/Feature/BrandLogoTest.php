<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BrandLogoTest extends TestCase
{
    public function test_empty_saved_logo_uses_cape_tennis_artwork(): void
    {
        foreach ([null, '', '   '] as $value) {
            Cache::put('site_setting.brand_logo_url', new SiteSetting(['value' => $value]));
            $this->assertSame('assets/img/logos/cape-tennis-logo-transparent.png', SiteSetting::brandLogoPath());
            $html = view('_partials.macros')->render();
            $this->assertStringContainsString('/assets/img/logos/cape-tennis-logo-transparent.png', $html);
        }
    }

    public function test_configured_logo_is_trimmed_and_preserved(): void
    {
        Cache::put('site_setting.brand_logo_url', new SiteSetting(['value' => '  https://example.com/logo.png  ']));
        $this->assertSame('https://example.com/logo.png', SiteSetting::brandLogoPath());
    }
}
