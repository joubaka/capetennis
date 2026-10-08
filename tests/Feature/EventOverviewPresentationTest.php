<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Draw;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventOverviewPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function overview(int $type): Event
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        $eventType = EventType::forceCreate(['name' => 'Demo event type', 'type' => $type]);
        $event = Event::factory()->create(['eventType' => $eventType->id, 'name' => 'Demo tournament', 'results_published' => 0]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);

        return $event;
    }

    public function test_team_overview_keeps_tools_and_finances_reachable_in_closed_sections(): void
    {
        $event = $this->overview(EventType::TEAM);
        Draw::factory()->create(['event_id' => $event->id]);
        $html = $this->get(route('admin.events.overview', $event))->assertOk()->getContent();
        $this->assertStringNotContainsString('>Event home</span>', $html);
        $this->assertStringContainsString('<summary>Setup tools</summary>', $html);
        $this->assertMatchesRegularExpression('/id="event-finance-details"\s*>/', $html);
        foreach (['admin.events.transactions', 'backend.team-selection.index', 'backend.event.clothing.index'] as $route) {
            $this->assertStringContainsString(route($route, $event), $html);
        }
        $this->assertStringContainsString('Needs attention', $html);
        $this->assertStringContainsString('Draws not ready to publish', $html);
        $this->assertLessThan(strpos($html, 'event-kpi-grid">'), strpos($html, 'Needs attention'));
        $this->assertStringContainsString('Team Stats', $html);
        $this->assertLessThan(strpos($html, '<summary>Setup tools'), strpos($html, 'Team Stats'));
        $this->assertStringContainsString('id="addExpenseModal"', $html);
        $this->assertStringContainsString("window.addEventListener('beforeprint'", $html);
        $this->assertStringContainsString("window.addEventListener('afterprint'", $html);

        $otherEvent = Event::factory()->create(['eventType' => $event->eventType]);
        $this->get(route('admin.events.overview', $otherEvent))->assertForbidden();
    }

    public function test_finance_validation_errors_reopen_the_section(): void
    {
        $event = $this->overview(EventType::TEAM);
        $this->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['amount' => 'Invalid amount']))]);
        $html = $this->get(route('admin.events.overview', $event))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="event-finance-details"\s+open\s*>/', $html);
    }

    public function test_individual_overview_keeps_publication_separate_and_unchanged(): void
    {
        $event = $this->overview(EventType::INDIVIDUAL);
        $html = $this->get(route('admin.events.overview', $event))->assertOk()->getContent();
        $this->assertStringContainsString('Results publication', $html);
        $this->assertStringContainsString('No outstanding operational warnings.', $html);
        $this->assertStringContainsString('action="'.route('result.publish', $event->id).'"', $html);
        $this->assertStringContainsString('Final positions remain private until you publish them.', $html);
        $this->assertStringNotContainsString('<summary>Setup tools', $html);
        $this->assertStringNotContainsString('id="event-finance-details"', $html);
        $this->assertStringNotContainsString('>Event home</span>', $html);
    }
}
