<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class BackendWorkspaceStylesTest extends TestCase
{
    public function test_event_more_menu_stays_above_page_toolbars(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2).'/public/css/backend-workspace.css');

        $this->assertMatchesRegularExpression(
            '/\.event-workspace-more \.dropdown-menu\s*\{[^}]*z-index:\s*1025;/s',
            $css
        );
    }
}
