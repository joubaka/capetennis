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
            ->assertSee('event-workspace-chrome', false)
            ->assertDontSee('draws-event-header', false)
            ->assertDontSee('id="menu-1"', false)
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
        foreach ([
            'admin.events.overview', 'headOffice.show', 'admin.events.entries.new',
            'admin.events.settings', 'admin.events.transactions',
            'admin.events.results.individual', 'admin.events.categories',
            'admin.events.announcements',
        ] as $route) {
            $url = route($route, $event);
            $this->assertStringContainsString($url, $html);
            $this->assertSame(1, substr_count($html, 'href="'.$url.'"'), $route.' should have one canonical navigation link.');
        }
        $this->assertStringNotContainsString(route('admin.events.draws', $event), $html);
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertLessThan(
            strpos($html, '>Finances</a>'),
            strpos($html, '>Results</a>'),
            'Results should be a primary event tab before Finances.'
        );
        $this->assertGreaterThan(
            strpos($html, '>More</button>'),
            strpos($html, '>Event directors</a>'),
            'Event directors should live in the More menu.'
        );

        $otherEvent = Event::factory()->create(['eventType' => 6]);
        $otherHtml = view('backend.event.partials.workspace-nav', ['event' => $otherEvent])->render();
        $this->assertStringNotContainsString('<a ', $otherHtml);
    }

    public function test_common_event_tools_are_unique_tabs_with_the_existing_role_and_regional_gates(): void
    {
        [, $event] = $this->eventAdmin();
        $render = fn (array $data = []) => view('backend.event.partials.workspace-nav', array_merge([
            'event' => $event, 'eventWorkspaceActive' => 'categories',
        ], $data))->render();
        $html = $render();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        foreach (['admin.events.categories', 'admin.events.finances'] as $route) {
            $url = route($route, $event);
            $this->assertSame(1, $xpath->query('//nav/a[@href="'.$url.'"]')->length);
            $this->assertSame(1, substr_count($html, 'href="'.$url.'"'));
        }
        $this->assertSame(1, $xpath->query('//nav/a[@href="'.route('admin.events.categories', $event).'" and @aria-current="page"]')->length);
        $this->assertStringNotContainsString('Player ratings', $html);
        foreach ([['eventWorkspaceRegionalOnly' => true], ['rosterOnly' => true]] as $context) {
            $restricted = $render($context);
            $this->assertStringNotContainsString(route('admin.events.categories', $event), $restricted);
            $this->assertStringNotContainsString(route('admin.events.finances', $event), $restricted);
        }
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $regional = $render(['eventWorkspaceRegionalOnly' => true, 'eventWorkspaceActive' => 'ratings']);
        $this->assertSame(1, substr_count($regional, 'href="'.route('backend.player-performance.event-ratings', $event).'"'));
        $this->assertSame(1, substr_count($regional, 'aria-current="page"'));
        $this->assertStringNotContainsString('event-workspace-more__toggle', $regional);
        $this->actingAs(User::factory()->create());
        $this->assertStringNotContainsString('<a ', $render());
    }

    public function test_event_overview_renders_the_operational_workspace_hierarchy(): void
    {
        [, $event] = $this->eventAdmin();

        $this->get(route('admin.events.overview', $event))
            ->assertOk()
            ->assertSee('event-overview-page', false)
            ->assertSee('event-workspace-chrome', false)
            ->assertSee('event-operations-title', false)
            ->assertSee('event-kpi-grid', false)
            ->assertDontSee('Manage Categories &amp; Entries', false)
            ->assertDontSee('Fixtures HQ')
            ->assertDontSee('Event Settings')
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

    public function test_legacy_event_main_opens_one_workspace_and_preserves_event_authorization(): void
    {
        [, $event] = $this->eventAdmin();

        $this->get(route('event.admin.main', $event))
            ->assertRedirect(route('headOffice.show', $event));

        $html = $this->get(route('headOffice.show', $event))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'id="layout-menu"'));
        $this->assertSame(1, substr_count($html, 'id="layout-navbar"'));
        $this->assertSame(1, substr_count($html, 'class="layout-wrapper '));
        $this->assertStringNotContainsString('id="drawsTab"', $html);
        $this->assertStringNotContainsString('assets/js/admin-main.js', $html);

        $otherEvent = Event::factory()->create(['eventType' => 6]);
        $this->get(route('event.admin.main', $otherEvent))->assertForbidden();
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
