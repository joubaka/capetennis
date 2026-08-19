<?php

namespace Tests\Feature;

use Illuminate\Routing\RouteCollection;
use Tests\TestCase;

class AuditInteractionViewSafetyTest extends TestCase
{
    public function test_audit_interaction_script_does_not_break_pages_when_route_cache_is_stale(): void
    {
        $router = app('router');
        $registeredRoutes = $router->getRoutes();

        $router->setRoutes(new RouteCollection());

        try {
            $rendered = view('layouts.sections.audit-interactions')->render();

            $this->assertStringContainsString('const endpoint = null;', $rendered);
        } finally {
            $router->setRoutes($registeredRoutes);
        }
    }
}
