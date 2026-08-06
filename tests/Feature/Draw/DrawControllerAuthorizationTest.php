<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Authorization tests for DrawController.
 *
 * Covers: togglePublish, createDraw, generateFromModal, updateSettings,
 *         saveGroups, generateRoundRobinFixtures, json (read), and
 *         addPlayerToDraw / removePlayer (player management).
 *
 * Expected access model:
 *   - Guest                → 401
 *   - Ordinary user        → 403
 *   - Admin / convenor     → permitted (role check only — DrawPolicy has no
 *                            event-admin scope, it checks hasAnyRole)
 *   - Super-user           → permitted (Gate::before bypass)
 *
 * Publish / generateFixtures abilities require admin (not convenor).
 */
class DrawControllerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;
    private User $admin;
    private User $convenor;
    private User $ordinaryUser;

    private Event $event;
    private Draw  $draw;
    private Draw  $lockedDraw;
    private Draw  $publishedDraw;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'convenor',   'guard_name' => 'web']);

        $this->event = Event::factory()->create();

        $this->draw = Draw::factory()->create([
            'event_id'  => $this->event->id,
            'published' => false,
            'locked'    => false,
        ]);

        $this->lockedDraw = Draw::factory()->create([
            'event_id'  => $this->event->id,
            'published' => false,
            'locked'    => true,
        ]);

        $this->publishedDraw = Draw::factory()->create([
            'event_id'  => $this->event->id,
            'published' => true,
            'locked'    => false,
        ]);

        $this->superUser    = User::factory()->create()->assignRole('super-user');
        $this->admin        = User::factory()->create()->assignRole('admin');
        $this->convenor     = User::factory()->create()->assignRole('convenor');
        $this->ordinaryUser = User::factory()->create();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // A. togglePublish  (POST draw/publishToggle/{id})
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_toggle_publish(): void
    {
        $this->postJson(route('draw.toggle.publish', $this->draw->id))
            ->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_toggle_publish(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('draw.toggle.publish', $this->draw->id))
            ->assertForbidden();
    }

    public function test_convenor_cannot_toggle_publish(): void
    {
        // DrawPolicy::publish requires admin, not convenor
        $this->actingAs($this->convenor)
            ->postJson(route('draw.toggle.publish', $this->draw->id))
            ->assertForbidden();
    }

    public function test_admin_can_toggle_publish(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('draw.toggle.publish', $this->draw->id))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_super_user_can_toggle_publish(): void
    {
        $this->actingAs($this->superUser)
            ->postJson(route('draw.toggle.publish', $this->draw->id))
            ->assertOk();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // B. createDraw  (POST draw/{event}/create)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_create_draw(): void
    {
        $this->postJson(route('backend.draw.create', $this->event), ['drawName' => 'X'])
            ->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_create_draw(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('backend.draw.create', $this->event), ['drawName' => 'X'])
            ->assertForbidden();
    }

    public function test_admin_can_create_draw(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('backend.draw.create', $this->event), ['drawName' => 'New Draw'])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_convenor_can_create_draw(): void
    {
        $this->actingAs($this->convenor)
            ->postJson(route('backend.draw.create', $this->event), ['drawName' => 'Conv Draw'])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_super_user_can_create_draw(): void
    {
        $this->actingAs($this->superUser)
            ->postJson(route('backend.draw.create', $this->event), ['drawName' => 'SU Draw'])
            ->assertOk();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // C. generateFromModal  (POST /draws/generate-from-modal)
    // ─────────────────────────────────────────────────────────────────────────

    private function modalPayload(): array
    {
        return [
            'event_id'       => $this->event->id,
            'draw_name'      => 'Modal Draw',
            'draw_format_id' => 1,
        ];
    }

    public function test_guest_cannot_generate_from_modal(): void
    {
        $this->postJson(route('draws.generate.from.modal'), $this->modalPayload())
            ->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_generate_from_modal(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('draws.generate.from.modal'), $this->modalPayload())
            ->assertForbidden();
    }

    public function test_admin_can_generate_from_modal(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('draws.generate.from.modal'), $this->modalPayload());

        // Returns a redirect (non-JSON route) — just ensure not 401/403
        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    public function test_super_user_can_generate_from_modal(): void
    {
        $response = $this->actingAs($this->superUser)
            ->postJson(route('draws.generate.from.modal'), $this->modalPayload());

        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // D. updateSettings  (PUT /admin/draws/{draw}/update-settings)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_update_settings(): void
    {
        $this->putJson(route('admin.draws.update-settings', $this->draw), ['boxes' => 4])
            ->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_update_settings(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->putJson(route('admin.draws.update-settings', $this->draw), ['boxes' => 4])
            ->assertForbidden();
    }

    public function test_locked_draw_cannot_be_updated_by_admin(): void
    {
        $this->actingAs($this->admin)
            ->putJson(route('admin.draws.update-settings', $this->lockedDraw), ['boxes' => 4])
            ->assertForbidden();
    }

    public function test_admin_can_update_settings(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson(route('admin.draws.update-settings', $this->draw), ['boxes' => 4]);

        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    public function test_super_user_can_update_settings(): void
    {
        $response = $this->actingAs($this->superUser)
            ->putJson(route('admin.draws.update-settings', $this->draw), ['boxes' => 4]);

        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // E. generateRoundRobinFixtures  (POST /admin/draws/{draw}/generate-roundrobin)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_generate_roundrobin(): void
    {
        $this->postJson(route('admin.draws.generateRoundRobin', $this->draw))
            ->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_generate_roundrobin(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('admin.draws.generateRoundRobin', $this->draw))
            ->assertForbidden();
    }

    public function test_convenor_cannot_generate_roundrobin_on_unlocked_draw(): void
    {
        // DrawPolicy::generateFixtures requires admin (not convenor)
        $this->actingAs($this->convenor)
            ->postJson(route('admin.draws.generateRoundRobin', $this->draw))
            ->assertForbidden();
    }

    public function test_admin_cannot_generate_roundrobin_on_published_draw(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.draws.generateRoundRobin', $this->publishedDraw))
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // F. json read endpoint  (GET /draw/{draw}/json)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_read_draw_json(): void
    {
        $this->getJson(route('json', $this->draw))
            ->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_read_draw_json(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->getJson(route('json', $this->draw))
            ->assertForbidden();
    }

    public function test_admin_can_read_draw_json(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('json', $this->draw))
            ->assertOk()
            ->assertJsonStructure(['fixtureMap', 'isDrawLocked']);
    }

    public function test_convenor_can_read_draw_json(): void
    {
        $this->actingAs($this->convenor)
            ->getJson(route('json', $this->draw))
            ->assertOk();
    }

    public function test_super_user_can_read_draw_json(): void
    {
        $this->actingAs($this->superUser)
            ->getJson(route('json', $this->draw))
            ->assertOk();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // G. addPlayerToDraw  (POST /admin/draws/add-player)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_add_player_to_draw(): void
    {
        $this->postJson(route('admin.draws.addPlayerToDraw'), [
            'draw_id'    => $this->draw->id,
            'player_ids' => [999],
        ])->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_add_player_to_draw(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('admin.draws.addPlayerToDraw'), [
                'draw_id'    => $this->draw->id,
                'player_ids' => [999],
            ])->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // H. removePlayer  (POST /admin/draws/remove-player)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_remove_player_from_draw(): void
    {
        $this->postJson(route('admin.draws.removePlayer'), [
            'draw_id'         => $this->draw->id,
            'registration_id' => 1,
        ])->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_remove_player_from_draw(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('admin.draws.removePlayer'), [
                'draw_id'         => $this->draw->id,
                'registration_id' => 1,
            ])->assertForbidden();
    }
}
