<?php

namespace Tests\Feature;

use App\Models\Event;
use Tests\TestCase;

class EventAssistantPanelTest extends TestCase
{
    public function test_event_panel_renders_real_routes_and_assets_without_participant_data(): void
    {
        $user = \Mockery::mock(\App\Models\User::class)->makePartial();
        $user->shouldReceive('hasAnyRole')->andReturn(false);
        $user->shouldReceive('hasRole')->with('super-user')->andReturn(true);
        $this->actingAs($user);
        $event = new Event;
        $event->id = 801;
        $event->name = 'Assistant demonstration event';
        $html = view('backend.event.partials.assistant', compact('event'))->render();
        $this->assertStringContainsString('/backend/events/801/assistant/message', $html);
        $this->assertStringContainsString('event-assistant.js', $html);
        $this->assertStringContainsString('event-assistant.css', $html);
        $this->assertStringContainsString('Review the transcript before sending', $html);
        if (getenv('CT_ASSISTANT_QA_HTML') === '1') {
            $directory = storage_path('framework/testing');
            if (! is_dir($directory)) mkdir($directory, 0755, true);
            file_put_contents($directory.'/event-assistant.html', '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="test"></head><body><button data-event-assistant-open aria-controls="event-assistant-panel" aria-expanded="false">Ask assistant</button>'.$html.'</body></html>');
        }
    }

    public function test_panel_assets_and_markup_are_absent_without_super_user_role(): void
    {
        $user = \Mockery::mock(\App\Models\User::class)->makePartial();
        $user->shouldReceive('hasAnyRole')->andReturn(false);
        $user->shouldReceive('hasRole')->with('super-user')->andReturn(false);
        $this->actingAs($user);
        $event = new Event;
        $event->id = 801;
        $event->name = 'Restricted pilot';
        $html = view('backend.event.partials.assistant', compact('event'))->render();
        $this->assertStringNotContainsString('event-assistant-panel', $html);
        $this->assertStringNotContainsString('event-assistant.js', $html);
    }
}
