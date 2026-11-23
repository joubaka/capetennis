<?php

namespace Tests\Unit\Draw;

use App\Models\{CtrEventCertification, Event, User};
use App\Services\Draw\{CtrCertificationStatus, CtrPlannerAccess};
use Illuminate\Support\Facades\Schema;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CtrCertificationStatusTest extends TestCase
{
    public function test_guest_does_not_receive_a_planner_link(): void
    {
        $this->assertFalse(app(CtrPlannerAccess::class)->manages(null, (new Event)->forceFill(['eventType' => 1])));
    }
    public function test_missing_table_omits_badges_without_querying_a_relation_and_checks_schema_once(): void
    {
        Schema::shouldReceive('hasTable')->once()->with('ctr_event_certifications')->andReturn(false);
        $first = new Event;
        $second = new Event;
        $this->assertFalse($first->isCtrCertified());
        $this->assertFalse($second->isCtrCertified());
        $this->assertFalse($first->relationLoaded('ctrCertification'));
        $this->assertFalse($second->relationLoaded('ctrCertification'));
    }

    public function test_available_table_retains_loaded_certification_metadata(): void
    {
        Schema::shouldReceive('hasTable')->once()->with('ctr_event_certifications')->andReturn(true);
        $event = new Event;
        $event->setRelation('ctrCertification', (new CtrEventCertification)->forceFill(['certified' => true]));
        $this->assertTrue($event->isCtrCertified());
        $event->setRelation('ctrCertification', null);
        $this->assertFalse($event->isCtrCertified());
    }

    public function test_planner_reports_setup_pending_when_certification_schema_is_missing(): void
    {
        Schema::shouldReceive('hasTable')->once()->with('ctr_event_certifications')->andReturn(false);
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->andReturn(true);
        $event = (new Event)->forceFill(['id' => 5, 'eventType' => 1]);
        try {
            app(CtrPlannerAccess::class)->authorize($user, $event, false);
            $this->fail('Expected unavailable planner.');
        } catch (HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
            $this->assertStringContainsString('CTR planner setup is pending', $exception->getMessage());
        }
    }

    public function test_partial_schema_keeps_badges_available_but_planner_unavailable(): void
    {
        Schema::shouldReceive('hasTable')->once()->with('ctr_event_certifications')->andReturn(true);
        Schema::shouldReceive('hasTable')->once()->with('ctr_draw_plans')->andReturn(false);
        $status = app(CtrCertificationStatus::class);
        $this->assertTrue($status->available());
        $this->assertFalse($status->plannerAvailable());
        $this->assertFalse($status->plannerAvailable());
    }
}
