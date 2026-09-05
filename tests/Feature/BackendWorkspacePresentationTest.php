<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackendWorkspacePresentationTest extends TestCase
{
    use RefreshDatabase;

    private function eventAdmin(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create(['eventType' => 6, 'name' => 'Workspace <event>']);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);

        return [$admin, $event];
    }

    public function test_backend_shell_is_explicit_and_does_not_style_the_public_home(): void
    {
        [, $event] = $this->eventAdmin();
        $this->get(route('headOffice.show', $event))->assertOk()
            ->assertSee('class="ct-backend"', false)
            ->assertSee('css/backend-workspace.css', false)
            ->assertSee('layout-horizontal', false)
            ->assertSee('Workspace &lt;event&gt;', false)
            ->assertDontSee('user-scalable=no', false);

        $this->get('/')->assertOk()
            ->assertDontSee('class="ct-backend"', false)
            ->assertDontSee('css/backend-workspace.css', false)
            ->assertSee('class="ct-frontend"', false)
            ->assertSee('css/frontend-workspace.css', false);
    }

    public function test_event_navigation_preserves_context_and_hides_other_event_actions(): void
    {
        [, $event] = $this->eventAdmin();
        $html = view('backend.event.partials.workspace-nav', [
            'event' => $event, 'eventWorkspaceActive' => 'draws',
        ])->render();
        foreach (['admin.events.overview', 'headOffice.show', 'admin.events.entries.new', 'admin.events.settings'] as $route) {
            $this->assertStringContainsString(route($route, $event), $html);
        }
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));

        $otherEvent = Event::factory()->create(['eventType' => 6]);
        $otherHtml = view('backend.event.partials.workspace-nav', ['event' => $otherEvent])->render();
        $this->assertStringNotContainsString('<a ', $otherHtml);
    }

    public function test_event_overview_renders_the_operational_workspace_hierarchy(): void
    {
        [, $event] = $this->eventAdmin();

        $this->get(route('admin.events.overview', $event))
            ->assertOk()
            ->assertSee('event-overview-page', false)
            ->assertSee('event-operations-title', false)
            ->assertSee('event-kpi-grid', false)
            ->assertSee('Registration, payment and draw readiness at a glance');
    }

    public function test_legacy_draw_index_redirects_only_for_an_authorized_event(): void
    {
        [, $event] = $this->eventAdmin();
        $this->get(route('draw.index', ['id' => $event->id]))
            ->assertRedirect(route('headOffice.show', $event));
        $otherEvent = Event::factory()->create(['eventType' => 6]);
        $this->get(route('draw.index', ['id' => $otherEvent->id]))->assertForbidden();
        $this->get(route('draw.index'))->assertNotFound();
    }

    public function test_event_modal_partials_render_the_existing_form_contracts(): void
    {
        [, $event] = $this->eventAdmin();
        $drawModal = view('backend.adminPage.admin_show.tabs.modals.generateDrawOptionsModal', [
            'eventCategories' => collect(), 'event' => $event,
        ])->render();
        $this->assertStringContainsString('id="generateDrawModal"', $drawModal);
        $this->assertStringContainsString(route('draws.generate.from.modal'), $drawModal);
        $this->assertStringContainsString('name="_token"', $drawModal);
        $nominationModal = view('backend.adminPage.admin_show.tabs.modals.nominationModal', [
            'players' => collect(),
        ])->render();
        $this->assertStringContainsString('id="nominationForm"', $nominationModal);
        $this->assertStringContainsString('id="nominateSelect2"', $nominationModal);
    }
}
