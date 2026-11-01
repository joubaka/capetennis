<?php

namespace Tests\Feature;

use App\Models\{AssistantTimingObservation, Draw, DrawSetting, Event, EventAssistantConversation, EventAssistantMessage, Fixture, Registration, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventAssistantTimingTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        config(['event-assistant.enabled' => true]);
        Http::preventStrayRequests();
        $this->travelTo(now()->setDate(2026, 11, 8)->setTime(12, 0));
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        $event = Event::factory()->create(['start_date' => '2026-11-07', 'end_date' => '2026-11-08']);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'match_status' => 1,
            'registration1_id' => Registration::factory()->create()->id,
            'registration2_id' => Registration::factory()->create()->id]);
        $this->actingAs($actor);

        return [$actor, $event, $draw, $fixture];
    }

    private function timing(Fixture $fixture): array
    {
        return ['fixture_kind' => 'individual', 'fixture_id' => $fixture->id, 'actual_start' => '2026-11-07 09:00', 'actual_finish' => '2026-11-07 09:42', 'verified' => true, 'interrupted' => false];
    }

    public function test_verified_observation_is_idempotent_and_does_not_change_match_or_schedule(): void
    {
        [, $event, , $fixture] = $this->scenario();
        $attributes = $fixture->fresh()->getAttributes();
        foreach ([1, 2] as $_) $this->postJson(route('backend.event-assistant.timing-observations', $event), $this->timing($fixture))->assertOk();
        $this->assertDatabaseCount('event_assistant_timing_observations', 1);
        $this->assertSame(42, AssistantTimingObservation::first()->actual_minutes);
        $this->assertSame($attributes, $fixture->fresh()->getAttributes());
        $this->assertDatabaseCount('order_of_plays', 0);
        $this->assertDatabaseCount('published_schedule_assignments', 0);
    }

    public function test_foreign_fixture_unverified_future_and_in_progress_observations_are_rejected(): void
    {
        [, $event, , $fixture] = $this->scenario();
        $foreign = Fixture::factory()->create();
        $this->postJson(route('backend.event-assistant.timing-observations', $event), array_replace($this->timing($fixture), ['fixture_id' => $foreign->id]))->assertNotFound();
        $this->postJson(route('backend.event-assistant.timing-observations', $event), array_replace($this->timing($fixture), ['verified' => false]))->assertUnprocessable();
        $this->postJson(route('backend.event-assistant.timing-observations', $event), array_replace($this->timing($fixture), ['actual_finish' => '2026-11-09 10:00']))->assertUnprocessable();
        $fixture->update(['match_status' => 2]);
        $this->postJson(route('backend.event-assistant.timing-observations', $event), $this->timing($fixture))->assertUnprocessable();
        $this->assertDatabaseCount('event_assistant_timing_observations', 0);
    }

    public function test_non_manager_cannot_record_or_read_duration_data(): void
    {
        [, $event, $draw, $fixture] = $this->scenario();
        $this->actingAs(User::factory()->create());
        $this->postJson(route('backend.event-assistant.timing-observations', $event), $this->timing($fixture))->assertForbidden();
        $this->getJson(route('backend.event-assistant.duration-suggestion', [$event, 'draw_id' => $draw->id]))->assertForbidden();
    }

    public function test_insufficient_data_falls_back_and_timed_match_rule_cannot_be_learned_away(): void
    {
        [, $event, $draw] = $this->scenario();
        $url = route('backend.event-assistant.duration-suggestion', [$event, 'draw_id' => $draw->id]);
        $this->getJson($url)->assertOk()->assertJsonPath('source', 'configured_default')->assertJsonPath('samples', 0);
        \App\Models\EventAssistantPreference::create(['event_id' => $event->id, 'user_id' => auth()->id(), 'preferences' => ['duration' => 80]]);
        $this->getJson($url)->assertOk()->assertJsonPath('minutes', 80);
        DrawSetting::factory()->create(['draw_id' => $draw->id, 'score_format' => 'timed', 'match_duration_minutes' => 25, 'changeover_minutes' => 5]);
        $this->getJson($url)->assertOk()->assertJsonPath('source', 'timed_match_rule')->assertJsonPath('minutes', 25);
        $this->assertDatabaseCount('order_of_plays', 0);
    }

    public function test_pruning_is_bounded_dry_run_by_default_and_cascades_expired_private_content(): void
    {
        [$actor, $event] = $this->scenario();
        foreach ([1, 2, 3] as $index) {
            $conversation = EventAssistantConversation::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $actor->id, 'event_id' => $event->id, 'expires_at' => $index === 3 ? now()->addDay() : now()->subDay()]);
            $message = EventAssistantMessage::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Private synthetic answer']);
            \App\Models\EventAssistantFeedback::create(['message_id' => $message->id, 'user_id' => $actor->id, 'rating' => 'helpful', 'category' => 'other']);
            \App\Models\EventAssistantProposal::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'conversation_id' => $conversation->id,
                'event_id' => $event->id, 'user_id' => $actor->id, 'type' => 'schedule', 'payload' => [],
                'fingerprint' => str_repeat('a', 64), 'token_hash' => str_repeat('b', 64), 'expires_at' => $conversation->expires_at]);
        }
        $this->artisan('assistant:prune', ['--limit' => 1])->assertSuccessful();
        $this->assertDatabaseCount('event_assistant_conversations', 3);
        $this->artisan('assistant:prune', ['--apply' => true, '--limit' => 1])->assertSuccessful();
        $this->assertDatabaseCount('event_assistant_conversations', 2);
        $this->assertDatabaseCount('event_assistant_messages', 2);
        $this->assertDatabaseCount('event_assistant_feedback', 2);
        $this->assertDatabaseCount('event_assistant_proposals', 2);
        $this->artisan('assistant:prune', ['--apply' => true, '--limit' => 100])->assertSuccessful();
        $this->assertDatabaseCount('event_assistant_conversations', 1);
        $this->assertDatabaseCount('event_assistant_messages', 1);
        $this->assertDatabaseCount('event_assistant_feedback', 1);
        $this->assertDatabaseCount('event_assistant_proposals', 1);
    }
}
