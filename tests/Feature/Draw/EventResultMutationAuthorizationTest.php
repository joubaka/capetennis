<?php

namespace Tests\Feature\Draw;

use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Player;
use App\Models\Position;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventResultMutationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private CategoryEvent $categoryEvent;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->event = Event::factory()->create(['results_published' => false]);
        $this->categoryEvent = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert([
            'event_id' => $this->event->id,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_individual_results_page_is_event_scoped(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.events.results.individual', $this->event))
            ->assertForbidden();

        $otherEvent = Event::factory()->create();
        $this->actingAs($this->admin)
            ->get(route('admin.events.results.individual', $otherEvent))
            ->assertForbidden();

        $this->get(route('admin.events.results.individual', $this->event))
            ->assertOk()
            ->assertSee('Save & Publish Results')
            ->assertSee('aria-current="page"', false);
    }

    public function test_result_publish_is_post_only_and_event_scoped(): void
    {
        $this->assertSame(['POST'], Route::getRoutes()->getByName('result.publish')->methods());

        $this->actingAs(User::factory()->create())
            ->postJson(route('result.publish', $this->event))
            ->assertForbidden();
        $this->assertFalse($this->event->fresh()->results_published);

        DB::table('category_results')->insert([
            'event_id' => $this->event->id,
            'category_id' => $this->categoryEvent->category_id,
            'registration_id' => Registration::factory()->create()->id,
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('result.publish', $this->event))
            ->assertOk();
        $this->assertTrue($this->event->fresh()->results_published);
    }

    public function test_individual_results_cannot_be_published_before_positions_are_saved(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('result.publish', $this->event))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('results');

        $this->assertFalse($this->event->fresh()->results_published);
    }

    public function test_category_result_save_is_authorized_and_event_scoped(): void
    {
        $registration = Registration::factory()->create();
        $this->categoryEvent->registrations()->attach($registration->id, ['status' => 'active']);
        $payload = ['positions' => [['registration_id' => $registration->id, 'position' => 1]]];

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.events.categories.results.store', [$this->event, $this->categoryEvent]), $payload)
            ->assertForbidden();

        $otherEvent = Event::factory()->create();
        DB::table('event_admins')->insert([
            'event_id' => $otherEvent->id,
            'user_id' => $this->admin->id,
        ]);
        $this->actingAs($this->admin)
            ->postJson(route('admin.events.categories.results.store', [$otherEvent, $this->categoryEvent]), $payload)
            ->assertNotFound();

        $this->postJson(route('admin.events.categories.results.store', [$this->event, $this->categoryEvent]), $payload)
            ->assertOk();
        $this->assertDatabaseHas('category_results', [
            'event_id' => $this->event->id,
            'category_id' => $this->categoryEvent->category_id,
            'registration_id' => $registration->id,
            'position' => 1,
        ]);
    }

    public function test_category_result_save_rejects_registration_outside_category_and_can_clear_results(): void
    {
        $outsider = Registration::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.categories.results.store', [$this->event, $this->categoryEvent]), [
                'positions' => [['registration_id' => $outsider->id, 'position' => 1]],
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('positions');

        DB::table('category_results')->insert([
            'event_id' => $this->event->id,
            'category_id' => $this->categoryEvent->category_id,
            'registration_id' => $outsider->id,
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson(route('admin.events.categories.results.store', [$this->event, $this->categoryEvent]), [
            'positions' => [],
        ])->assertOk();
        $this->assertDatabaseCount('category_results', 0);
    }

    public function test_position_reset_is_event_scoped(): void
    {
        Position::create([
            'category_event_id' => $this->categoryEvent->id,
            'player_id' => Player::factory()->create()->id,
            'position' => 1,
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('positions.reset'), ['category_event_id' => $this->categoryEvent->id])
            ->assertForbidden();
        $this->assertDatabaseCount('positions', 1);

        $this->actingAs($this->admin)
            ->postJson(route('positions.reset'), ['category_event_id' => $this->categoryEvent->id])
            ->assertOk();
        $this->assertDatabaseCount('positions', 0);
    }

    public function test_position_order_rejects_player_outside_category(): void
    {
        $registeredPlayer = Player::factory()->create();
        $registration = Registration::factory()->create();
        $registration->players()->attach($registeredPlayer->id);
        $this->categoryEvent->registrations()->attach($registration->id);
        $outsider = Player::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('result.save.order', $this->categoryEvent), [
                'order' => [$registeredPlayer->id, $outsider->id],
            ])->assertUnprocessable();
        $this->assertDatabaseCount('positions', 0);

        $this->postJson(route('result.save.order', $this->categoryEvent), [
            'order' => [['id' => $registeredPlayer->id, 'position' => 1]],
        ])->assertOk();
        $this->assertDatabaseHas('positions', [
            'category_event_id' => $this->categoryEvent->id,
            'player_id' => $registeredPlayer->id,
            'position' => 1,
        ]);
    }
}
