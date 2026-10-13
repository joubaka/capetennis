<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Services\EventLifecycleService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventRegistrationWindowTest extends TestCase
{
    public function test_toggle_and_inclusive_closing_date_are_the_only_window_controls(): void
    {
        foreach (['draft', 'closed', 'scheduled', 'active', 'completed', 'archived'] as $status) {
            $event = new Event(['signUp' => true, 'status' => $status, 'start_date' => '2026-11-06', 'deadline' => 14]);
            $this->assertTrue($event->registrationIsOpen(Carbon::parse('2026-10-23 23:59:59')));
            $this->assertFalse($event->registrationIsOpen(Carbon::parse('2026-10-24 00:00:00')));
            $event->signUp = false;
            $this->assertFalse($event->registrationIsOpen(Carbon::parse('2026-10-13')));
        }
    }

    public function test_publication_remains_separate_and_closing_date_is_not_mutated(): void
    {
        $event = new Event(['published' => false, 'signUp' => true, 'start_date' => '2026-11-06', 'deadline' => 14]);
        $at = Carbon::parse('2026-10-23 12:00:00');
        $this->assertTrue($event->registrationIsOpen($at));
        $this->assertFalse((new EventLifecycleService())->snapshot($event, $at)['entries_open']);
        $event->published = true;
        $this->assertTrue((new EventLifecycleService())->snapshot($event, $at)['entries_open']);
        $this->assertSame('2026-11-06', $event->start_date->toDateString());
    }

    public function test_no_configured_closing_date_uses_the_toggle(): void
    {
        $event = new Event(['signUp' => true]);
        $this->assertTrue($event->registrationIsOpen());
        $event->signUp = false;
        $this->assertFalse($event->registrationIsOpen());
    }
}
