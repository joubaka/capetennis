<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventExpense;
use App\Models\EventVenueConvenor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventVenueConvenorBatchTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = Event::factory()->create(['name' => 'Platteland Tennis Tournament Primary Schools 2026']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        EventAdmin::create(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
    }

    private function url(?Event $event = null): string
    {
        return route('admin.events.finances.venue-convenor.store', $event ?? $this->event);
    }

    public function test_multiple_names_normalize_and_retries_keep_exact_counts_with_event_isolation(): void
    {
        $foreign = Event::factory()->create();
        EventVenueConvenor::create(['event_id' => $foreign->id, 'name' => 'Alex Smith']);
        $existing = EventVenueConvenor::create(['event_id' => $this->event->id, 'name' => 'Already Listed']);
        $names = ['  Alex   Smith  ', 'Sam "Ace" O\'Brien', 'alex smith', 'Already Listed'];
        $first = $this->actingAs($this->admin)->postJson($this->url(), ['names' => $names, 'event_id' => $foreign->id]);
        $first->assertOk()->assertJsonPath('added', 2)->assertJsonPath('skipped', 2)->assertJsonCount(3, 'venueConvenors')
            ->assertJsonPath('venueConvenors.0.name', 'Alex Smith');
        $second = $this->postJson($this->url(), ['names' => $names]);
        $second->assertOk()->assertJsonPath('added', 0)->assertJsonPath('skipped', 4)->assertJsonCount(3, 'venueConvenors');
        $this->assertSame($first->json('venueConvenors'), $second->json('venueConvenors'));
        $this->assertDatabaseCount('event_venue_convenors', 4);
        $this->assertSame(3, EventVenueConvenor::where('event_id', $this->event->id)->count());
        $this->assertSame(1, EventVenueConvenor::where('event_id', $foreign->id)->count());
        $this->assertDatabaseHas('event_venue_convenors', ['id' => $existing->id, 'name' => 'Already Listed']);
    }

    public function test_invalid_batch_creates_no_partial_names(): void
    {
        $this->actingAs($this->admin);
        foreach ([['Valid Name', ' '], ['Valid Name', str_repeat('x', 151)], ['Valid Name', ['not' => 'a name']], []] as $names) {
            $this->postJson($this->url(), ['names' => $names])->assertUnprocessable()->assertJsonValidationErrors($names ? 'names.1' : 'names');
            $this->assertDatabaseCount('event_venue_convenors', 0);
        }
        $this->postJson($this->url(), ['names' => array_fill(0, 51, 'A Name')])->assertUnprocessable()->assertJsonValidationErrors('names');
        $this->assertDatabaseCount('event_venue_convenors', 0);
    }

    public function test_guest_normal_user_and_foreign_event_admin_cannot_add_names(): void
    {
        $this->postJson($this->url(), ['names' => ['Unauthorised']])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson($this->url(), ['names' => ['Unauthorised']])->assertForbidden();
        $this->actingAs($this->admin)->postJson($this->url(Event::factory()->create()), ['names' => ['Foreign event']])->assertForbidden();
        $this->assertDatabaseCount('event_venue_convenors', 0);
    }

    public function test_legacy_single_name_response_and_duplicate_validation_are_preserved(): void
    {
        $response = $this->actingAs($this->admin)->postJson($this->url(), ['name' => '  Legacy   Convenor  ']);
        $response->assertOk()->assertJsonPath('venueConvenor.name', 'Legacy Convenor')->assertJsonStructure(['venueConvenor' => ['id', 'name', 'destroy_url']]);
        $this->postJson($this->url(), ['name' => 'legacy convenor'])->assertUnprocessable();
        $this->postJson($this->url(), ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('event_venue_convenors', 1);
    }

    public function test_modal_and_expense_dropdowns_render_names_safely_without_generic_ajax_reload_hook(): void
    {
        $name = 'Sam "Ace" <img src=x onerror=alert(1)>';
        EventVenueConvenor::create(['event_id' => $this->event->id, 'name' => $name]);
        EventExpense::create(['event_id' => $this->event->id, 'expense_type' => 'balls', 'amount' => 1]);
        $response = $this->actingAs($this->admin)->get(route('admin.events.finances', $this->event))->assertOk();
        $response->assertSee('name="names[]"', false)->assertSee('Add another')->assertSee('Add convenors')
            ->assertSee('vcBatchErrors')->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertSee('new Option(vc.name, vc.name)', false);
        $html = $response->getContent();
        preg_match('/<form[^>]*id="addVenueConvenorForm"[^>]*>/', $html, $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringNotContainsString('data-ajax', $matches[0]);
        if (getenv('CT_EVENT_EXPENSE_QA')) {
            $directory = storage_path('app/event-expenses-qa');
            if (! is_dir($directory)) { mkdir($directory, 0755, true); }
            file_put_contents($directory.'/venue-convenors.html', $html);
        }
    }
}
