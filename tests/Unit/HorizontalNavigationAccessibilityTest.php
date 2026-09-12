<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class HorizontalNavigationAccessibilityTest extends TestCase
{
    public function test_navigation_exposes_current_page_and_mobile_focus_management(): void
    {
        $menu = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/sections/menu/horizontalMenu.blade.php');
        $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/horizontalLayout.blade.php');

        $this->assertStringContainsString('aria-label="Primary navigation"', $menu);
        $this->assertStringContainsString('aria-current="page"', $menu);
        $this->assertStringContainsString('$menuItemIsActive', $menu);
        $this->assertStringContainsString("menu.querySelector('a, button')?.focus()", $layout);
        $this->assertStringContainsString("event.key === 'Tab'", $layout);
    }
}
