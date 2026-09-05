<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\OrderOfPlay;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BulkDrawPublicationTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->event = Event::factory()->create(['eventType' => 6]);
        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
    }

    public function test_event_admin_can_publish_multiple_ready_draws_at_once(): void
    {
        $draws = collect([$this->readyDraw(), $this->readyDraw()]);

        $this->actingAs($this->admin)->postJson(route('backend.event-draws.bulk-publication', $this->event), [
            'operation' => 'draws',
            'draw_ids' => $draws->pluck('id')->all(),
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'published')
            ->assertJsonCount(0, 'failed');

        $this->assertSame(2, Draw::whereIn('id', $draws->pluck('id'))->where('published', true)->count());
    }

    public function test_bulk_schedule_publication_only_changes_prepared_selected_draws(): void
    {
        $prepared = $this->readyDraw();
        $notPrepared = $this->readyDraw();
        $fixture = $prepared->drawFixtures()->first();
        OrderOfPlay::create([
            'draw_id' => $prepared->id,
            'fixture_id' => $fixture->id,
            'venue_id' => DB::table('venues')->insertGetId(['name' => 'Main venue']),
            'court' => '1',
            'time' => '2026-09-20 09:00:00',
        ]);

        $this->actingAs($this->admin)->postJson(route('backend.event-draws.bulk-publication', $this->event), [
            'operation' => 'schedules',
            'draw_ids' => [$prepared->id, $notPrepared->id],
        ])->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonCount(1, 'published')
            ->assertJsonCount(1, 'failed');

        $this->assertTrue((bool) $prepared->fresh()->oop_published);
        $this->assertFalse((bool) $notPrepared->fresh()->oop_published);
    }

    public function test_bulk_publication_rejects_cross_event_ids_before_any_changes(): void
    {
        $local = $this->readyDraw();
        $foreignEvent = Event::factory()->create(['eventType' => 6]);
        $foreign = Draw::factory()->create(['event_id' => $foreignEvent->id]);

        $this->actingAs($this->admin)->postJson(route('backend.event-draws.bulk-publication', $this->event), [
            'operation' => 'draws',
            'draw_ids' => [$local->id, $foreign->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('draw_ids');

        $this->assertFalse((bool) $local->fresh()->published);
    }

    public function test_non_admin_cannot_bulk_publish_draws(): void
    {
        $draw = $this->readyDraw();

        $this->actingAs(User::factory()->create())->postJson(route('backend.event-draws.bulk-publication', $this->event), [
            'operation' => 'draws',
            'draw_ids' => [$draw->id],
        ])->assertForbidden();

        $this->assertFalse((bool) $draw->fresh()->published);
    }

    public function test_overview_selection_is_forwarded_to_the_combined_scheduler(): void
    {
        $selected = $this->readyDraw();
        $other = $this->readyDraw();

        $response = $this->actingAs($this->admin)->get(route('backend.event-venue-schedule.index', [
            'event' => $this->event,
            'draw_ids' => [$selected->id],
        ]));

        $response->assertOk()
            ->assertSee('class="form-check-input draw-choice mt-0" type="checkbox" value="'.$selected->id.'" checked', false)
            ->assertSee('class="form-check-input draw-choice mt-0" type="checkbox" value="'.$other->id.'" ', false)
            ->assertDontSee('value="'.$other->id.'" checked', false);
    }

    private function readyDraw(): Draw
    {
        $draw = Draw::factory()->create([
            'event_id' => $this->event->id,
            'published' => false,
            'locked' => false,
        ]);
        $draw->settings()->create(['workflow' => 'round_robin']);
        $registration = Registration::factory()->create();
        $draw->registrations()->attach($registration->id);
        Fixture::factory()->create(['draw_id' => $draw->id]);

        return $draw;
    }
}
