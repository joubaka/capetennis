<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Services\EventLifecycleService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventLifecycleServiceTest extends TestCase
{
    public function test_existing_event_fields_project_to_expected_lifecycle_states(): void
    {
        $service = new EventLifecycleService();
        $now = Carbon::parse('2026-08-21 12:00:00');

        $this->assertSame('draft', $service->state(new Event(['published' => false]), $now));
        $this->assertSame('published_open', $service->state(new Event([
            'published' => true, 'signUp' => true, 'start_date' => '2026-09-01', 'deadline' => 7,
        ]), $now));
        $this->assertSame('live', $service->state(new Event([
            'published' => true, 'signUp' => false, 'start_date' => '2026-08-20', 'end_date' => '2026-08-22',
        ]), $now));
        $this->assertSame('results_published', $service->state(new Event([
            'published' => true, 'results_published' => true,
        ]), $now));
        $this->assertSame('entries_closed', $service->state(new Event([
            'published' => true, 'signUp' => false,
        ]), $now));
        $this->assertSame('completed', $service->state(new Event([
            'status' => 'complete', 'published' => true,
        ]), $now));
        $this->assertSame('archived', $service->state(new Event(['status' => 'archived']), $now));
    }

    public function test_snapshot_exposes_operator_friendly_labels(): void
    {
        $service = new EventLifecycleService();
        $now = Carbon::parse('2026-08-21 12:00:00');

        $open = new Event([
            'published' => true,
            'signUp' => true,
            'start_date' => '2026-09-01',
            'deadline' => 7,
        ]);

        $this->assertSame('Entries Open', $service->snapshot($open, $now)['label']);
        $this->assertSame('Entries Closed', $service->snapshot(new Event([
            'published' => true,
            'signUp' => false,
        ]), $now)['label']);
        $this->assertSame('Results Published', $service->snapshot(new Event([
            'published' => true,
            'results_published' => true,
        ]), $now)['label']);
    }

    public function test_event_status_label_uses_the_shared_lifecycle_instead_of_entry_records(): void
    {
        Carbon::setTestNow('2026-08-21 12:00:00');

        try {
            $event = new Event([
                'published' => true,
                'signUp' => true,
                'start_date' => '2026-09-01',
                'deadline' => 7,
            ]);

            $this->assertSame('Entries Open', $event->status_label);
        } finally {
            Carbon::setTestNow();
        }
    }
}
