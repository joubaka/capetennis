<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EventCreateWorkflowMarkupTest extends TestCase
{
    #[Test]
    public function new_event_form_supports_paste_extraction_and_requires_preview_confirmation(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/backend/event/create.blade.php');

        $this->assertStringContainsString('id="event-brief"', $view);
        $this->assertStringContainsString('id="fill-event-brief"', $view);
        $this->assertStringContainsString("fetch(document.getElementById('event-brief-card').dataset.previewUrl", $view);
        $this->assertStringContainsString('id="eventPreviewModal"', $view);
        $this->assertStringContainsString('Nothing has been saved yet.', $view);
        $this->assertStringContainsString("if (form.dataset.confirmed === 'true') return;", $view);
        $this->assertStringContainsString("form.dataset.confirmed = 'true';", $view);
        $this->assertStringContainsString('form.requestSubmit();', $view);
    }

    #[Test]
    public function copy_event_keeps_its_existing_direct_save_workflow(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/backend/event/create.blade.php');

        $this->assertStringContainsString("@unless(\$isCopy)", $view);
        $this->assertStringContainsString("\$isCopy ? 'Save Copied Event' : 'Preview Event'", $view);
    }
}
