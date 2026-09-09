<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicEventPresentationTest extends TestCase
{
    public static function eventTypeTemplates(): array
    {
        return [
            ['individual.blade.php'],
            ['team.blade.php'],
            ['interpro.blade.php'],
            ['masters.blade.php'],
            ['cavaliers_trials.blade.php'],
            ['parentChildDoubles.blade.php'],
        ];
    }

    #[DataProvider('eventTypeTemplates')]
    public function test_event_type_templates_use_the_shared_information_presentation(string $template): void
    {
        $html = file_get_contents(resource_path('views/frontend/event/eventTypes/'.$template));

        $this->assertStringContainsString(
            "frontend.event.partials.event-information",
            $html
        );
        $this->assertStringContainsString(
            "frontend.event.partials.event-announcements",
            $html
        );
    }

    public function test_masters_only_flattens_columns_below_the_desktop_breakpoint(): void
    {
        $html = file_get_contents(resource_path('views/frontend/event/eventTypes/masters.blade.php'));

        $this->assertStringContainsString('@media(max-width:991.98px)', $html);
        $this->assertStringNotContainsString(
            '.masters-public-event .masters-flow{display:flex;flex-direction:column}.masters-public-event .masters-flow>.col-xl-8',
            preg_replace('/@media\(max-width:991\.98px\)\{.*$/s', '', $html)
        );
    }

    public function test_generic_event_view_is_not_empty(): void
    {
        $html = file_get_contents(resource_path('views/frontend/event/eventTypes/default.blade.php'));

        $this->assertStringContainsString('frontend.event.partials.event-information', $html);
        $this->assertStringContainsString('frontend.event.partials.event-about', $html);
        $this->assertStringContainsString('Event documents', $html);
    }

    public function test_team_region_picker_uses_viewport_safe_wrapped_buttons_on_every_screen_size(): void
    {
        foreach (['team.blade.php', 'interpro.blade.php'] as $template) {
            $html = file_get_contents(resource_path('views/frontend/event/eventTypes/'.$template));
            $this->assertStringContainsString('frontend.event.partials._region_team_picker', $html);
            $this->assertStringNotContainsString('flex-nowrap overflow-auto', $html);
        }

        $picker = file_get_contents(resource_path('views/frontend/event/partials/_region_team_picker.blade.php'));
        $this->assertStringContainsString('region-tab-grid', $picker);
        $this->assertStringContainsString('grid-template-columns: 1fr', $picker);
        $this->assertStringContainsString('grid-template-columns: repeat(auto-fit, minmax(180px, 1fr))', $picker);
        $this->assertStringContainsString('overflow-wrap: anywhere', $picker);
        $this->assertStringContainsString('min-height: 48px', $picker);
        $this->assertStringContainsString('/<wbr>', $picker);
        $this->assertStringNotContainsString('region-team-select', $picker);
        $this->assertStringNotContainsString('overflow-auto', $picker);
    }
}
