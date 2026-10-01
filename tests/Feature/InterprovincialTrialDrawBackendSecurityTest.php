<?php

namespace Tests\Feature;

use App\Models\Draw;
use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InterprovincialTrialDrawBackendSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private Draw $draw;

    private User $assignedAdmin;

    private User $superUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $eventTypeId = DB::table('eventtypes')->insertGetId([
            'name' => 'Interprovincial Trials',
            'type' => EventType::INDIVIDUAL,
            'code' => EventType::INTERPROVINCIAL_TRIALS_CODE,
        ]);

        $this->assertNotSame(13, $eventTypeId, 'The regression fixture must prove type selection is not tied to legacy id 13.');

        $this->event = Event::factory()->create(['eventType' => $eventTypeId]);
        $this->draw = Draw::factory()->create(['event_id' => $this->event->id]);
        $this->assignedAdmin = User::factory()->create()->assignRole('admin');
        $this->superUser = User::factory()->create()->assignRole('super-user');

        DB::table('event_admins')->insert([
            'event_id' => $this->event->id,
            'user_id' => $this->assignedAdmin->id,
        ]);
    }

    public function test_interprovincial_draw_backend_uses_semantic_type_and_event_scoped_authorization(): void
    {
        $this->actingAs($this->assignedAdmin)
            ->get(route('headOffice.show', $this->event))
            ->assertOk()
            ->assertViewIs('backend.headOffice.individual-event-show');

        $this->actingAs($this->superUser)
            ->get(route('headOffice.show', $this->event))
            ->assertOk()
            ->assertViewIs('backend.headOffice.individual-event-show');

        $unassignedAdmin = User::factory()->create()->assignRole('admin');
        $ordinaryUser = User::factory()->create();

        $this->actingAs($unassignedAdmin)
            ->get(route('headOffice.show', $this->event))
            ->assertForbidden();

        $this->actingAs($ordinaryUser)
            ->get(route('headOffice.show', $this->event))
            ->assertForbidden();

        $otherEvent = Event::factory()->create(['eventType' => $this->event->eventType]);
        $this->actingAs($this->assignedAdmin)
            ->get(route('headOffice.show', $otherEvent))
            ->assertForbidden();
    }

    public function test_legacy_draw_link_redirects_to_full_workspace_and_team_generation_is_blocked(): void
    {
        $this->actingAs($this->assignedAdmin)
            ->get(route('admin.events.draws', $this->event))
            ->assertRedirect(route('headOffice.show', $this->event));
        $this->get(route('headOffice.show', $this->event))->assertOk()
            ->assertSee('class="ct-backend"', false)
            ->assertSee('Create &amp; choose format', false)
            ->assertDontSee('generate-fixtures-btn', false);
        $this->postJson(route('headoffice.createFixtures', $this->event))->assertUnprocessable();
        $this->assertDatabaseCount('draws', 1);
        $this->assertDatabaseCount('team_fixtures', 0);
        $this->actingAs(User::factory()->create()->assignRole('admin'))
            ->get(route('admin.events.draws', $this->event))->assertForbidden();
    }

    public function test_engine_mode_endpoints_are_super_user_only(): void
    {
        foreach ([$this->assignedAdmin, User::factory()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('engine.draw.show', $this->draw))
                ->assertForbidden();
            $this->actingAs($user)
                ->patch(route('engine.draw.update', $this->draw), ['engine_mode' => 'hybrid'])
                ->assertForbidden();
            $this->actingAs($user)
                ->post(route('engine.draw.rollback', $this->draw))
                ->assertForbidden();
            $this->actingAs($user)
                ->patch(route('engine.event.update', $this->event), ['engine_mode' => 'hybrid'])
                ->assertForbidden();
        }

        $this->assertNull($this->draw->fresh()->engine_mode);
        $this->assertNull($this->event->fresh()->engine_mode);

        $this->actingAs($this->superUser)
            ->get(route('engine.draw.show', $this->draw))
            ->assertOk();
        $this->actingAs($this->superUser)
            ->patch(route('engine.draw.update', $this->draw), ['engine_mode' => 'hybrid'])
            ->assertRedirect();
        $this->assertSame('hybrid', $this->draw->fresh()->engine_mode);

        $this->actingAs($this->superUser)
            ->post(route('engine.draw.rollback', $this->draw))
            ->assertRedirect();
        $this->assertSame('legacy', $this->draw->fresh()->engine_mode);

        $this->actingAs($this->superUser)
            ->patch(route('engine.event.update', $this->event), ['engine_mode' => 'hybrid'])
            ->assertRedirect();
        $this->assertSame('hybrid', $this->event->fresh()->engine_mode);
    }

    public function test_draw_engine_update_rejects_cross_event_nesting_tampering(): void
    {
        $otherEvent = Event::factory()->create(['eventType' => $this->event->eventType]);

        $this->actingAs($this->superUser)
            ->patch(route('engine.draw.update', $this->draw), [
                'event_id' => $otherEvent->id,
                'engine_mode' => 'canonical',
            ])
            ->assertNotFound();

        $this->assertNull($this->draw->fresh()->engine_mode);
        $this->assertNull($otherEvent->fresh()->engine_mode);
    }

    public function test_team_draw_creation_endpoints_reject_cross_event_categories_without_partial_writes(): void
    {
        $otherEvent = Event::factory()->create(['eventType' => $this->event->eventType]);
        $foreignCategory = \App\Models\CategoryEvent::factory()->create(['event_id' => $otherEvent->id]);
        $drawTypeId = DB::table('draw_types')->insertGetId([
            'drawTypeName' => 'Event-isolated round robin',
            'btn_color' => 'primary',
            'type' => 'team',
        ]);

        $countsBefore = collect(['draws', 'draw_settings', 'team_ties', 'team_fixtures'])
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);

        $payload = [
            'draw_type_id' => $drawTypeId,
            'drawName' => 'Tampered cross-event draw',
            'category_ids' => [$foreignCategory->id],
        ];

        $this->actingAs($this->superUser)
            ->postJson(route('headoffice.previewTeamDraw', $this->event), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_ids.0');

        $this->actingAs($this->superUser)
            ->postJson(route('headoffice.createSingleDraw.team', $this->event), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_ids.0');

        $this->actingAs($this->superUser)
            ->postJson(route('create.team.fixtures'), [
                'event_id' => $this->event->id,
                'drawType' => $drawTypeId,
                'category' => [$foreignCategory->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category.0');

        foreach ($countsBefore as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table.' must not receive partial cross-event records.');
        }
    }
}
