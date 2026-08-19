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

    public function test_result_publish_is_post_only_and_event_scoped(): void
    {
        $this->assertSame(['POST'], Route::getRoutes()->getByName('result.publish')->methods());

        $this->actingAs(User::factory()->create())
            ->postJson(route('result.publish', $this->event))
            ->assertForbidden();
        $this->assertFalse($this->event->fresh()->results_published);

        $this->actingAs($this->admin)
            ->postJson(route('result.publish', $this->event))
            ->assertOk();
        $this->assertTrue($this->event->fresh()->results_published);
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
