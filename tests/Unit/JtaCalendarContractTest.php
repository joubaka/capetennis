<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\V1\JtaIntegrationController;
use App\Models\Event;
use App\Services\PublicEventCalendarService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class JtaCalendarContractTest extends TestCase
{
    public function test_v1_calendar_keeps_the_public_contract_from_the_shared_source(): void
    {
        $event = new Event([
            'id' => 42,
            'name' => 'Spring Open',
            'information' => 'Published event',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-02',
        ]);
        $event->id = 42;

        $this->app->instance(PublicEventCalendarService::class, new class($event) extends PublicEventCalendarService {
            public function __construct(private readonly Event $event) {}

            public function upcoming(int $limit = 100): Collection
            {
                return collect([$this->event]);
            }
        });

        $response = app(JtaIntegrationController::class)->calendar(app(PublicEventCalendarService::class));
        $payload = $response->getData(true);

        $this->assertSame('calendar', $payload['meta']['result_type']);
        $this->assertSame('ct-event-42', $payload['data'][0]['source_id']);
        $this->assertSame('Spring Open', $payload['data'][0]['name']);
        $this->assertArrayHasKey('next', $payload['links']);
        $this->assertNotEmpty($response->headers->get('ETag'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=60', $response->headers->get('Cache-Control'));
    }
}
