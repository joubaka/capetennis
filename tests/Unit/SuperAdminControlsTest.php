<?php

namespace Tests\Unit;

use App\Support\SuperAdminControls;
use Illuminate\Routing\Route;
use Tests\TestCase;

class SuperAdminControlsTest extends TestCase
{
    public function test_only_exclusive_guards_are_marked(): void
    {
        $controls = new SuperAdminControls;
        $this->assertTrue($controls->exclusive((new Route('GET', 'private', fn () => null))->middleware('role:super-user')));
        $this->assertTrue($controls->exclusive((new Route('GET', 'private', fn () => null))->middleware('can:super-user')));
        $this->assertFalse($controls->exclusive((new Route('GET', 'shared', fn () => null))->middleware('role:super-user|admin')));
        $this->assertFalse($controls->exclusive(null));
    }

    public function test_existing_controller_guards_and_shared_actions_are_distinguished(): void
    {
        $controls = new SuperAdminControls;
        $this->assertTrue($controls->exclusive(app('router')->getRoutes()->getByName('admin.entry.details')));
        $this->assertTrue($controls->exclusive(app('router')->getRoutes()->getByName('engine.draw.show')));
        $this->assertFalse($controls->exclusive(app('router')->getRoutes()->getByName('backend.email-issues')));
        $this->assertTrue($controls->exclusive(app('router')->getRoutes()->getByName('admin.refunds.bank.index')));
    }

    public function test_assets_are_emitted_only_for_super_admin(): void
    {
        $this->assertStringNotContainsString('ct-super-admin-routes', view('layouts.sections.super-admin-controls')->render());
        foreach ([false, true] as $superAdmin) {
            $user = \Mockery::mock(\App\Models\User::class)->makePartial();
            $user->shouldReceive('hasRole')->with('super-user')->andReturn($superAdmin);
            $user->shouldReceive('hasAnyRole')->andReturn(false);
            auth()->setUser($user);
            $html = view('layouts.sections.super-admin-controls')->render();
            if ($superAdmin) {
                $this->assertStringContainsString('ct-super-admin-routes', $html);
                $this->assertStringContainsString('super-admin-controls.css', $html);
            } else {
                $this->assertStringNotContainsString('ct-super-admin-routes', $html);
                $this->assertStringNotContainsString('super-admin-controls.css', $html);
            }
        }
    }

    public function test_manifest_contains_route_patterns_methods_and_exclusive_flags(): void
    {
        $manifest = (new SuperAdminControls)->manifest();
        $this->assertNotEmpty($manifest);
        foreach ($manifest as [$pattern, $methods, $exclusive]) {
            $this->assertIsString($pattern);
            $this->assertIsArray($methods);
            $this->assertIsBool($exclusive);
        }
        // Also serves as the real routing fixture for the companion DOM/browser check.
        if ($path = getenv('CT_SUPER_ADMIN_MANIFEST_PATH')) {
            file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR));
        }
    }
}
