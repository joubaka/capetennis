<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FrontendWorkspaceStylesTest extends TestCase
{
    public function test_active_horizontal_menu_item_keeps_readable_text_and_background(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2).'/public/css/frontend-workspace.css');

        $this->assertMatchesRegularExpression(
            '/\.ct-frontend \.bg-menu-theme\.menu-horizontal \.menu-inner\s*>\s*\.menu-item\.active\s*>\s*\.menu-link\s*\{[^}]*color:\s*var\(--ct-ink\)\s*!important;[^}]*background:\s*var\(--ct-selected\)\s*!important;/s',
            $css
        );
    }
}
