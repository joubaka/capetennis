<?php

namespace Tests\Feature\Assistant;

use App\Models\{Category, CategoryEvent, Event, EventAssistantProposal, EventType, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Gate, Http};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantCategoryCopyTest extends TestCase
{
    use RefreshDatabase;

    private function setupEvents(): array
    {
        config(['event-assistant.enabled' => true]);
        Http::preventStrayRequests();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        $events = collect([1, 2, 3])->map(fn ($leg) => Event::factory()->create(['name' => 'Panarottis/Spur Leg '.$leg]));
        foreach ($events as $event) DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $category = Category::factory()->create(['name' => 'Under 14 Boys']);
        CategoryEvent::factory()->create(['event_id' => $events[0]->id, 'category_id' => $category->id, 'entry_fee' => 175, 'ordering' => 3, 'nominations_published' => true, 'locked_at' => now()]);
        $this->actingAs($actor);
        return [$actor, ...$events->all(), $category];
    }

    private function preview(Event $event, ?string $message = null): array
    {
        return $this->postJson(route('backend.event-assistant.message', $event), ['message' => $message ?? 'Copy categories from Panarottis/Spur Leg 1 to Legs 2 and 3, without creating duplicates.', 'use_ai' => true])->assertOk()->json();
    }

    private function confirm(Event $event, array $response)
    {
        return $this->postJson(route('backend.event-assistant.confirm', $event), ['proposal_uuid' => $response['proposal']['uuid'], 'confirmation_token' => $response['proposal']['confirmation_token'], 'confirmed' => true]);
    }

    public function test_preview_then_copy_to_two_events_preserves_source_and_skips_existing_and_retries(): void
    {
        [, $source, $two, $three, $category] = $this->setupEvents();
        $existing = CategoryEvent::factory()->create(['event_id' => $two->id, 'category_id' => $category->id, 'entry_fee' => 99, 'ordering' => 8]);
        $before = CategoryEvent::orderBy('id')->get()->map->getAttributes()->all();
        $response = $this->preview($source);
        $this->assertSame('category_copy', $response['proposal']['type']);
        $this->assertSame(1, $response['proposal']['preview']['count']);
        $this->assertSame('skip_existing', $response['proposal']['preview']['destinations'][0]['categories'][0]['action']);
        $this->assertEquals(99, $response['proposal']['preview']['destinations'][0]['categories'][0]['result_effective_fee']);
        $this->assertEquals(8, $response['proposal']['preview']['destinations'][0]['categories'][0]['result_ordering']);
        $this->assertEquals($before, CategoryEvent::orderBy('id')->get()->map->getAttributes()->all());
        $this->confirm($source, $response)->assertOk()->assertJsonPath('result.count', 1);
        $this->confirm($source, $response)->assertOk()->assertJsonPath('status', 'already_applied');
        $this->assertDatabaseCount('category_events', 3);
        $this->assertEquals($before[0], CategoryEvent::where('event_id', $source->id)->first()->getAttributes());
        $this->assertEquals($before[1], $existing->fresh()->getAttributes());
        $copy = CategoryEvent::where('event_id', $three->id)->firstOrFail();
        $this->assertEquals(175, $copy->entry_fee);
        $this->assertSame(3, $copy->ordering);
        $this->assertFalse((bool) $copy->nominations_published);
        $this->assertNull($copy->locked_at);
        foreach (['category_event_registrations', 'event_nominations', 'draws', 'positions', 'registration_orders'] as $table) $this->assertDatabaseCount($table, 0);
        $again = $this->preview($source);
        $this->assertSame(0, $again['proposal']['preview']['count']);
        $this->confirm($source, $again)->assertOk()->assertJsonPath('result.count', 0);
        Http::assertNothingSent();
    }

    public function test_equivalent_existing_name_is_skipped_even_with_another_category_id(): void
    {
        [, $source, $two] = $this->setupEvents();
        CategoryEvent::factory()->create(['event_id' => $two->id, 'category_id' => Category::factory()->create(['name' => 'UNDER 14 BOYS'])->id]);
        $response = $this->preview($source, "Copy categories from event #{$source->id} to event #{$two->id}");
        $this->assertSame(0, $response['proposal']['preview']['count']);
        $this->confirm($source, $response)->assertOk();
        $this->assertDatabaseCount('category_events', 2);
    }

    public function test_extra_constraints_are_not_silently_discarded(): void
    {
        [, $source, $two] = $this->setupEvents();
        $response = $this->preview($source, "Copy categories from event #{$source->id} to event #{$two->id} without copying fees");
        $this->assertNull($response['proposal']);
        $this->assertNotNull($response['clarification']);
        $this->assertDatabaseCount('category_events', 1);
    }

    public function test_changed_source_or_destination_rejects_the_entire_confirmation(): void
    {
        [, $source, $two, $three] = $this->setupEvents();
        $response = $this->preview($source);
        CategoryEvent::where('event_id', $source->id)->update(['entry_fee' => 200]);
        $this->confirm($source, $response)->assertConflict();
        $response = $this->preview($source);
        CategoryEvent::factory()->create(['event_id' => $three->id]);
        $this->confirm($source, $response)->assertConflict();
        $this->assertSame(0, $two->categoryEvents()->count());
        $this->assertSame(1, $three->categoryEvents()->count());
    }

    public function test_expiry_and_forged_token_do_not_copy(): void
    {
        [, $source] = $this->setupEvents();
        $response = $this->preview($source);
        $forged = $response;
        $forged['proposal']['confirmation_token'] = str_repeat('a', 64);
        $this->confirm($source, $forged)->assertUnprocessable();
        EventAssistantProposal::where('uuid', $response['proposal']['uuid'])->update(['expires_at' => now()->subMinute()]);
        $this->confirm($source, $response)->assertConflict();
        $this->assertDatabaseCount('category_events', 1);
    }

    public function test_ambiguous_missing_and_self_destination_requests_do_not_write_categories(): void
    {
        [, $source] = $this->setupEvents();
        Event::factory()->create(['name' => 'Panarottis/Spur Leg 2']);
        $response = $this->preview($source);
        $this->assertNull($response['proposal']);
        $this->assertNotNull($response['clarification']);
        $response = $this->preview($source, 'Copy categories from Missing Leg 1 to Missing Leg 2');
        $this->assertNull($response['proposal']);
        $this->postJson(route('backend.event-assistant.message', $source), ['message' => "Copy categories from event #{$source->id} to event #{$source->id}"])->assertUnprocessable();
        $this->assertDatabaseCount('category_events', 1);
    }

    public function test_destination_authorization_is_checked_at_preview_and_confirmation(): void
    {
        [$actor, $source, $two, $three] = $this->setupEvents();
        $response = $this->preview($source);
        $actor->removeRole('super-user');
        $this->confirm($source, $response)->assertForbidden(); // pilot gate rechecked
        DB::table('event_admins')->where('event_id', $three->id)->where('user_id', $actor->id)->delete();
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        try {
            app(\App\Services\Assistant\AssistantCategoryCopy::class)->review($actor, $source->id, [$two->id, $three->id]);
        } finally {
            $this->assertDatabaseCount('category_events', 1);
        }
    }

    public function test_trials_fee_override_is_rejected_and_null_override_can_copy(): void
    {
        [, $source, $two] = $this->setupEvents();
        $type = EventType::forceCreate(['name' => 'Interprovincial Trials', 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE, 'type' => 1]);
        $two->update(['eventType' => $type->id]);
        $message = "Copy categories from event #{$source->id} to event #{$two->id}";
        $this->postJson(route('backend.event-assistant.message', $source), ['message' => $message])->assertUnprocessable();
        CategoryEvent::where('event_id', $source->id)->update(['entry_fee' => null]);
        $response = $this->preview($source, $message);
        $this->assertNull($response['proposal']['preview']['destinations'][0]['categories'][0]['entry_fee']);
        $this->confirm($source, $response)->assertOk();
        $this->assertNull($two->categoryEvents()->first()->entry_fee);
    }
}
