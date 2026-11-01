<?php

namespace Tests\Feature\Assistant;

use App\Models\{Category, CategoryEvent, CategoryEventRegistration, Draw, DrawGroup, DrawGroupRegistration, DrawType, Event, EventAssistantConversation, EventAssistantProposal, Fixture, OrderOfPlay, Player, Registration, User, Venue};
use App\Services\Draw\FlexibleMonradService;
use App\Services\Scheduling\EventVenueScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        config(['event-assistant.enabled' => true, 'event-assistant.mode' => 'local_first']);
        Http::preventStrayRequests();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        $event = Event::factory()->create(['start_date' => '2026-11-07', 'end_date' => '2026-11-08']);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'U/14 Boys Draw', 'published' => true]);
        $venue = Venue::forceCreate(['name' => 'Main courts']);
        $event->venues()->attach($venue->id, ['num_courts' => 2]);
        $draw->venues()->attach($venue->id, ['num_courts' => 2]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 1, 'match_nr' => 1, 'registration1_id' => Registration::factory()->create()->id, 'registration2_id' => Registration::factory()->create()->id]);
        $this->actingAs($actor);
        return [$event, $actor, $draw, $venue, $fixture];
    }

    public function test_ordinary_event_admin_is_blocked_from_every_assistant_endpoint_before_validation_or_side_effects(): void
    {
        [$event] = $this->scenario();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $this->assertTrue($admin->can('event.manage', $event));
        $endpoints = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with((string) $route->getName(), 'backend.event-assistant.'));
        $this->assertGreaterThanOrEqual(14, $endpoints->count());
        foreach ($endpoints as $endpoint) {
            $url = route($endpoint->getName(), ['event' => $event, 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
            $this->json($endpoint->methods()[0], $url)->assertForbidden();
        }
        $this->assertDatabaseCount('event_assistant_conversations', 0);
        $this->assertDatabaseCount('event_assistant_proposals', 0);
        $this->assertDatabaseCount('event_assistant_timing_observations', 0);
        $this->assertDatabaseCount('order_of_plays', 0);
        Http::assertNothingSent();
    }

    private function proposal(Event $event, Draw $draw, Venue $venue): array
    {
        return $this->postJson(route('backend.event-assistant.preview', $event), ['options' => ['date' => '2026-11-07', 'start_time' => '08:00', 'end_time' => '17:00', 'draw_ids' => [$draw->id], 'venue_ids' => [$venue->id], 'courts' => ['1'], 'duration' => 60, 'court_gap' => 5, 'player_rest' => 60]])->assertOk()->json();
    }

    public function test_explicit_empty_draw_and_venue_selections_get_friendly_errors_without_defaulting_or_mutation(): void
    {
        [$event, , $draw, $venue] = $this->scenario();
        foreach (['preview', 'publication-preview'] as $endpoint) {
            foreach ([
                ['draw_ids', ['date' => '2026-11-07', 'draw_ids' => [], 'venue_ids' => [$venue->id]], 'Choose at least one draw before requesting a preview.'],
                ['venue_ids', ['date' => '2026-11-07', 'draw_ids' => [$draw->id], 'venue_ids' => []], 'Choose at least one venue before requesting a preview.'],
            ] as [$field, $options, $message]) {
                $this->postJson(route('backend.event-assistant.'.$endpoint, $event), ['options' => $options])
                    ->assertUnprocessable()->assertJsonPath('message', $message)->assertJsonPath('errors.'.$field.'.0', $message);
            }
        }
        $this->assertDatabaseCount('event_assistant_conversations', 0);
        $this->assertDatabaseCount('event_assistant_proposals', 0);
        $this->assertDatabaseCount('fixtures', 1);
        $this->assertDatabaseCount('order_of_plays', 0);
        $this->assertDatabaseCount('published_schedule_assignments', 0);
    }

    public function test_event_without_draws_explains_setup_without_creating_tournament_records(): void
    {
        $this->scenario();
        $event = Event::factory()->create(['start_date' => '2026-11-07', 'end_date' => '2026-11-07']);
        $this->postJson(route('backend.event-assistant.preview', $event), ['options' => ['date' => '2026-11-07']])
            ->assertOk()->assertJsonPath('proposal', null)
            ->assertJsonPath('clarification', 'This event has no draws yet. Create and prepare a draw before scheduling its matches.');
        $this->assertSame(0, $event->draws()->count());
        $this->assertDatabaseCount('event_assistant_proposals', 0);
        $this->assertDatabaseCount('order_of_plays', 0);
    }

    public function test_missing_venue_setup_explains_next_step_without_preview_or_booking_mutation(): void
    {
        [$event, , $draw] = $this->scenario();
        $draw->venues()->detach();
        $event->venues()->detach();
        $this->postJson(route('backend.event-assistant.preview', $event), ['options' => ['date' => '2026-11-07', 'draw_ids' => [$draw->id]]])
            ->assertOk()->assertJsonPath('proposal', null)
            ->assertJsonPath('clarification', 'Assign event venues and courts in the scheduling workspace first.');
        $this->assertDatabaseCount('event_assistant_proposals', 0);
        $this->assertDatabaseCount('fixtures', 1);
        $this->assertDatabaseCount('order_of_plays', 0);
    }

    public function test_existing_booking_stays_exactly_fixed_when_previewing_and_saving_new_matches(): void
    {
        [$event, , $draw, $venue, $fixture] = $this->scenario();
        OrderOfPlay::create(['fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id, 'court' => '2', 'time' => '2026-11-07 11:00:00', 'duration_minutes' => 45, 'gap_minutes' => 10]);
        $booking = OrderOfPlay::where('fixture_id', $fixture->id)->firstOrFail();
        $before = $booking->getAttributes();
        $new = Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 1, 'registration1_id' => Registration::factory()->create()->id, 'registration2_id' => Registration::factory()->create()->id]);
        $response = $this->proposal($event, $draw, $venue);
        $this->assertCount(1, $response['proposal']['preview']['matches']);
        $this->assertSame($new->id, $response['proposal']['preview']['matches'][0]['fixture_id']);
        $this->assertSame($before, $booking->fresh()->getAttributes());
        $this->confirm($event, $response)->assertOk();
        $this->assertSame($before, $booking->fresh()->getAttributes());
        $this->assertDatabaseCount('order_of_plays', 2);
        $this->assertDatabaseCount('published_schedule_assignments', 0);
    }

    public function test_timed_draw_preview_does_not_override_saved_playing_time_or_changeover(): void
    {
        [$event, , $draw, $venue] = $this->scenario();
        $draw->settings()->create(['score_format' => 'timed', 'match_duration_minutes' => 30, 'changeover_minutes' => 7]);
        $base = ['date' => '2026-11-07', 'draw_ids' => [$draw->id], 'venue_ids' => [$venue->id], 'duration' => 60, 'court_gap' => 5];
        $this->postJson(route('backend.event-assistant.preview', $event), ['options' => $base])->assertUnprocessable()->assertSee('30 minutes of play');
        $response = $this->postJson(route('backend.event-assistant.preview', $event), ['options' => array_replace($base, ['duration' => 30, 'court_gap' => 7])])->assertOk()->json();
        $this->assertSame(30, $response['proposal']['preview']['matches'][0]['duration']);
        $this->assertSame(7, $response['proposal']['preview']['matches'][0]['court_gap']);
        $this->assertDatabaseCount('order_of_plays', 0);
    }

    public function test_u19_feed_in_existing_bookings_survive_incremental_preview_and_confirm(): void
    {
        [$event, $actor] = $this->scenario();
        $event->update(['name' => 'Anonymized U19 Interpro', 'start_date' => '2026-10-30', 'end_date' => '2026-11-01']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => 'Under 19 Boys'])->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'drawName' => 'U/19 Boys feed-in']);
        $draw->settings()->create(['workflow' => 'feed_in_monrad', 'playoff_config' => ['challenge_playoffs' => true, 'challenge_pairs' => [2, 4, 6, 8, 10, 12, 14]]]);
        $venue = Venue::forceCreate(['name' => 'Isolated U19 courts']);
        $draw->venues()->attach($venue->id, ['num_courts' => 8]);
        $slots = [];
        foreach (Registration::factory()->count(16)->create() as $index => $registration) {
            $registration->categoryEvents()->attach($category->id, ['status' => 'registered', 'payment_status_id' => 1]);
            $path = strtr(str_pad(decbin($index), 4, '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
            $slots[$path] = ['type' => 'player', 'id' => $registration->id];
        }
        $compiler = app(FlexibleMonradService::class);
        $compiler->save($draw, ['size' => 16, 'slots' => $slots], 0);
        $record = $compiler->generate($draw, 1);
        $initial = app(EventVenueScheduleService::class)->preview($event, ['start' => '2026-10-30 08:00:00', 'end' => '2026-10-30 18:00:00', 'duration' => 30, 'court_gap' => 0, 'player_rest' => 20, 'wave_minutes' => 30, 'draw_ids' => [$draw->id], 'venue_ids' => [$venue->id]]);
        $targetId = $record->fixture_map['challenge_2'];
        $this->assertCount(43, $initial['matches']);
        foreach ($initial['matches'] as $row) {
            if ($row['fixture_id'] === $targetId) continue;
            OrderOfPlay::create(['fixture_id' => $row['fixture_id'], 'draw_id' => $draw->id, 'venue_id' => $row['venue_id'], 'court' => $row['court'], 'time' => $row['scheduled_at'], 'duration_minutes' => 30, 'gap_minutes' => 0]);
        }
        $before = OrderOfPlay::where('draw_id', $draw->id)->orderBy('id')->get()->map->getAttributes()->all();
        $response = $this->postJson(route('backend.event-assistant.preview', $event), ['options' => ['date' => '2026-10-30', 'start_time' => '08:00', 'end_time' => '18:00', 'duration' => 30, 'court_gap' => 0, 'player_rest' => 20, 'draw_ids' => [$draw->id], 'venue_ids' => [$venue->id]]])->assertOk()->json();
        $this->assertSame([], $response['proposal']['preview']['unscheduled']);
        $this->assertCount(1, $response['proposal']['preview']['matches']);
        $this->assertSame($targetId, $response['proposal']['preview']['matches'][0]['fixture_id']);
        $this->assertSame($before, OrderOfPlay::where('draw_id', $draw->id)->orderBy('id')->get()->map->getAttributes()->all());
        $this->confirm($event, $response)->assertOk();
        $this->assertSame($before, OrderOfPlay::where('draw_id', $draw->id)->where('fixture_id', '!=', $targetId)->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame(43, OrderOfPlay::where('draw_id', $draw->id)->count());
        $this->assertFalse((bool) $draw->fresh()->oop_published);
        $this->assertDatabaseCount('published_schedule_assignments', 0);
    }

    private function confirm(Event $event, array $response): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('backend.event-assistant.confirm', $event), ['proposal_uuid' => $response['proposal']['uuid'], 'confirmation_token' => $response['proposal']['confirmation_token'], 'confirmed' => true]);
    }

    public function test_preview_is_non_mutating_and_confirmation_is_idempotent_without_publication(): void
    {
        [$event, , $draw, $venue] = $this->scenario();
        $before = DB::table('fixtures')->count();
        $response = $this->proposal($event, $draw, $venue);
        $this->assertCount(1, $response['proposal']['preview']['matches']);
        $this->assertDatabaseCount('fixtures', $before);
        $this->assertDatabaseCount('order_of_plays', 0);
        $this->assertDatabaseCount('published_schedule_assignments', 0);
        $this->assertDatabaseCount('draw_audit_logs', 0);
        $this->confirm($event, $response)->assertOk()->assertJsonPath('status', 'applied');
        $this->confirm($event, $response)->assertOk()->assertJsonPath('status', 'already_applied');
        $this->assertDatabaseCount('order_of_plays', 1);
        $this->assertDatabaseCount('published_schedule_assignments', 0);
        $this->assertFalse((bool) $draw->fresh()->oop_published);
        $this->assertDatabaseCount('event_assistant_proposals', 1);
    }

    public function test_foreign_event_draws_and_conversations_are_rejected(): void
    {
        [$event, $actor, $draw, $venue] = $this->scenario();
        $response = $this->proposal($event, $draw, $venue);
        $other = Event::factory()->create();
        DB::table('event_admins')->insert(['event_id' => $other->id, 'user_id' => $actor->id]);
        $this->postJson(route('backend.event-assistant.preview', $other), ['conversation_uuid' => $response['conversation_uuid'], 'options' => ['date' => '2026-11-07', 'draw_ids' => [$draw->id]]])->assertNotFound();
        $this->postJson(route('backend.event-assistant.preview', $other), ['options' => ['date' => $other->start_date->toDateString(), 'draw_ids' => [$draw->id]]])->assertUnprocessable();
        $this->confirm($other, $response)->assertNotFound();
        $this->assertDatabaseCount('order_of_plays', 0);
    }

    public function test_non_manager_and_revoked_manager_cannot_access_or_confirm(): void
    {
        [$event, $actor, $draw, $venue] = $this->scenario();
        $response = $this->proposal($event, $draw, $venue);
        $actor->removeRole('super-user');
        $this->getJson(route('backend.event-assistant.context', $event))->assertForbidden();
        $this->confirm($event, $response)->assertForbidden();
        $this->assertDatabaseCount('order_of_plays', 0);
    }

    public function test_confirmation_is_actor_bound_and_requires_exact_token(): void
    {
        [$event, , $draw, $venue] = $this->scenario();
        $response = $this->proposal($event, $draw, $venue);
        $this->postJson(route('backend.event-assistant.confirm', $event), ['proposal_uuid' => $response['proposal']['uuid'], 'confirmation_token' => str_repeat('x', 64), 'confirmed' => true])->assertUnprocessable();
        $second = User::factory()->create()->assignRole('super-user');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $second->id]);
        $this->actingAs($second);
        $this->confirm($event, $response)->assertNotFound();
        $this->assertDatabaseCount('order_of_plays', 0);
    }

    public function test_stale_expired_and_tampered_proposals_cannot_save(): void
    {
        [$event, , $draw, $venue, $fixture] = $this->scenario();
        $response = $this->proposal($event, $draw, $venue);
        $fixture->update(['match_status' => 1]);
        $this->confirm($event, $response)->assertUnprocessable();
        $fixture->update(['match_status' => 0]);
        $proposal = EventAssistantProposal::where('uuid', $response['proposal']['uuid'])->firstOrFail();
        $proposal->update(['expires_at' => now()->subMinute()]);
        $this->confirm($event, $response)->assertConflict();
        $proposal->update(['expires_at' => now()->addMinute(), 'payload' => ['options' => [], 'revision' => 'modified']]);
        $this->confirm($event, $response)->assertConflict();
        $this->assertDatabaseCount('order_of_plays', 0);
    }

    public function test_new_conversation_message_invalidates_old_confirmation_and_supports_local_follow_up(): void
    {
        [$event, , $draw, $venue] = $this->scenario();
        $response = $this->proposal($event, $draw, $venue);
        $this->postJson(route('backend.event-assistant.message', $event), ['conversation_uuid' => $response['conversation_uuid'], 'message' => 'Start at 9 instead'])->assertOk()->assertJsonPath('options.start_time', '09:00');
        $this->confirm($event, $response)->assertConflict();
        $this->assertDatabaseCount('order_of_plays', 0);
        Http::assertNothingSent();
    }

    public function test_publication_needs_its_own_exact_confirmation_and_stale_snapshot_is_rejected(): void
    {
        [$event, , $draw, $venue] = $this->scenario();
        $saved = $this->proposal($event, $draw, $venue);
        $this->confirm($event, $saved)->assertOk();
        $publication = $this->postJson(route('backend.event-assistant.publication-preview', $event), ['options' => ['date' => '2026-11-07', 'draw_ids' => [$draw->id]]])->assertOk()->assertJsonPath('proposal.type', 'publication')->json();
        $this->assertDatabaseCount('published_schedule_assignments', 0);
        OrderOfPlay::query()->update(['time' => '2026-11-07 09:00:00']);
        $this->confirm($event, $publication)->assertUnprocessable();
        $publication = $this->postJson(route('backend.event-assistant.publication-preview', $event), ['options' => ['date' => '2026-11-07', 'draw_ids' => [$draw->id]]])->assertOk()->json();
        $this->confirm($event, $publication)->assertOk()->assertJsonPath('result.published', true);
        $this->confirm($event, $publication)->assertOk()->assertJsonPath('status', 'already_applied');
        $this->assertDatabaseCount('published_schedule_assignments', 1);
    }

    public function test_feedback_is_owned_deduplicated_and_aggregate_does_not_expose_text(): void
    {
        [$event, $actor, $draw, $venue] = $this->scenario();
        $response = $this->proposal($event, $draw, $venue);
        $feedback = ['message_id' => $response['message']['id'], 'rating' => 'unhelpful', 'reason' => 'wrong draw', 'comment' => 'Private correction details'];
        $this->postJson(route('backend.event-assistant.feedback', $event), $feedback)->assertOk();
        $this->postJson(route('backend.event-assistant.feedback', $event), $feedback)->assertOk();
        $this->assertDatabaseCount('event_assistant_feedback', 1);
        $this->getJson(route('backend.event-assistant.improvements', $event))->assertOk()->assertJsonPath('model_training', false)->assertJsonPath('feedback.0.category', 'understanding')->assertDontSee('Private correction details');
        $other = User::factory()->create()->assignRole('super-user');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $other->id]);
        $this->actingAs($other)->postJson(route('backend.event-assistant.feedback', $event), $feedback)->assertNotFound();
    }

    public function test_hidden_or_locked_draw_after_publication_preview_cannot_publish(): void
    {
        [$event, , $draw, $venue] = $this->scenario();
        $this->confirm($event, $this->proposal($event, $draw, $venue))->assertOk();
        foreach (['published' => false, 'locked' => true] as $field => $value) {
            $draw->update(['published' => true, 'locked' => false]);
            $response = $this->postJson(route('backend.event-assistant.publication-preview', $event), ['options' => ['date' => '2026-11-07', 'draw_ids' => [$draw->id]]])->assertOk()->json();
            $draw->update([$field => $value]);
            $this->confirm($event, $response)->assertConflict();
            $this->assertDatabaseCount('published_schedule_assignments', 0);
        }
    }

    public function test_follow_up_after_confirm_retains_date_draw_and_timing_context(): void
    {
        [$event, , $draw, $venue] = $this->scenario();
        $response = $this->proposal($event, $draw, $venue);
        $this->confirm($event, $response)->assertOk();
        $this->postJson(route('backend.event-assistant.message', $event), ['conversation_uuid' => $response['conversation_uuid'], 'message' => 'Review publication'])->assertOk()->assertJsonPath('proposal.type', 'publication')->assertJsonPath('options.date', '2026-11-07')->assertJsonPath('options.draw_ids.0', $draw->id);
    }

    public function test_preferences_require_confirmation_and_are_event_and_user_specific(): void
    {
        [$event, , $draw, $venue] = $this->scenario();
        $url = route('backend.event-assistant.preferences', $event);
        $this->putJson($url, ['preferences' => ['player_rest' => 90]])->assertUnprocessable();
        $this->putJson($url, ['preferences' => ['player_rest' => 90], 'confirmed' => true])->assertOk();
        $this->getJson(route('backend.event-assistant.context', $event))->assertOk()->assertJsonPath('defaults.player_rest', 90);
        $this->assertDatabaseCount('event_assistant_preferences', 1);
    }

    public function test_local_only_blocks_ai_and_voice_before_network_and_does_not_send_ordinary_questions(): void
    {
        [$event] = $this->scenario();
        config(['event-assistant.mode' => 'local_only', 'services.gemini.api_key' => 'test-key']);
        $this->postJson(route('backend.event-assistant.message', $event), ['message' => 'Schedule Saturday', 'use_ai' => true])->assertStatus(503);
        $this->postJson(route('backend.event-assistant.transcribe', $event), ['use_ai' => true])->assertForbidden();
        $this->postJson(route('backend.event-assistant.message', $event), ['message' => 'What can you do?'])->assertOk();
        Http::assertNothingSent();
    }

    public function test_new_draw_creates_only_confirmed_empty_draft_and_never_replaces_existing_fixtures(): void
    {
        [$event, , $existing] = $this->scenario();
        DrawType::forceCreate(['drawTypeName' => 'Singles', 'btn_color' => 'primary', 'type' => 'individual']);
        $response = $this->postJson(route('backend.event-assistant.message', $event), ['message' => 'Create draw named Under 16 Girls'])->assertOk()->assertJsonPath('proposal.type', 'draw_draft')->json();
        $this->assertDatabaseCount('draws', 1);
        $this->confirm($event, $response)->assertOk()->assertJsonPath('result.generated', false);
        $this->confirm($event, $response)->assertOk()->assertJsonPath('status', 'already_applied');
        $this->assertDatabaseCount('draws', 2);
        $this->assertDatabaseCount('fixtures', 1);
        $new = Draw::where('drawName', 'Under 16 Girls')->firstOrFail();
        $this->assertFalse((bool) $new->published);
        $this->assertSame($event->id, $new->event_id);
    }

    private function preparedRoundRobin(Event $event, User $actor): Draw
    {
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'drawName' => 'Prepared Singles', 'published' => false]);
        $draw->settings()->create(['workflow' => 'round_robin']);
        $group = DrawGroup::create(['draw_id' => $draw->id, 'name' => 'A']);
        foreach ([1, 2, 3] as $seed) {
            $registration = Registration::factory()->create();
            $registration->players()->attach(Player::factory()->create()->id);
            CategoryEventRegistration::create(['category_event_id' => $category->id, 'registration_id' => $registration->id, 'user_id' => $actor->id, 'status' => 'active', 'payment_status_id' => 1]);
            $draw->registrations()->attach($registration->id, ['seed' => $seed]);
            DrawGroupRegistration::create(['draw_group_id' => $group->id, 'registration_id' => $registration->id, 'seed' => $seed]);
        }
        return $draw;
    }

    public function test_initial_round_robin_generation_requires_review_and_preserves_other_draw_state(): void
    {
        [$event, $actor, $existing] = $this->scenario();
        $draw = $this->preparedRoundRobin($event, $actor);
        $response = $this->postJson(route('backend.event-assistant.generation-preview', $event), ['draw_id' => $draw->id])->assertOk()->assertJsonPath('proposal.type', 'draw_generate')->assertJsonPath('proposal.preview.count', 3)->json();
        $this->assertDatabaseCount('fixtures', 1);
        $this->confirm($event, $response)->assertOk()->assertJsonPath('result.count', 3)->assertJsonPath('result.generated', true);
        $this->confirm($event, $response)->assertOk()->assertJsonPath('status', 'already_applied');
        $this->assertDatabaseCount('fixtures', 4);
        $this->assertSame(1, $existing->drawFixtures()->count());
        $this->assertSame(3, $draw->drawFixtures()->where('stage', 'RR')->count());
        $this->assertDatabaseCount('order_of_plays', 0);
        $this->assertDatabaseCount('published_schedule_assignments', 0);
        $this->postJson(route('backend.event-assistant.generation-preview', $event), ['draw_id' => $draw->id])->assertConflict();
    }

    public function test_initial_round_robin_generation_rejects_changed_seeds_and_withdrawn_members(): void
    {
        [$event, $actor] = $this->scenario();
        $draw = $this->preparedRoundRobin($event, $actor);
        $response = $this->postJson(route('backend.event-assistant.generation-preview', $event), ['draw_id' => $draw->id])->assertOk()->json();
        $member = $draw->groups()->first()->groupRegistrations()->first();
        $member->update(['seed' => 8]);
        $this->confirm($event, $response)->assertConflict();
        $member->update(['seed' => 1]);
        $originalSets = $draw->settings?->num_sets;
        $draw->settings()->update(['num_sets' => (int) $originalSets === 3 ? 1 : 3]);
        $this->confirm($event, $response)->assertConflict();
        $draw->settings()->update(['num_sets' => $originalSets]);
        CategoryEventRegistration::where('registration_id', $member->registration_id)->update(['payment_status_id' => null]);
        $this->confirm($event, $response)->assertUnprocessable();
        CategoryEventRegistration::where('registration_id', $member->registration_id)->update(['payment_status_id' => 1]);
        CategoryEventRegistration::where('registration_id', $member->registration_id)->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        $this->confirm($event, $response)->assertUnprocessable();
        $this->assertSame(0, $draw->drawFixtures()->count());
    }

    public function test_initial_generation_rejects_cross_event_draw_and_duplicate_group_entrants(): void
    {
        [$event, $actor] = $this->scenario();
        $draw = $this->preparedRoundRobin($event, $actor);
        $other = Event::factory()->create();
        DB::table('event_admins')->insert(['event_id' => $other->id, 'user_id' => $actor->id]);
        $this->postJson(route('backend.event-assistant.generation-preview', $other), ['draw_id' => $draw->id])->assertNotFound();
        $group = $draw->groups()->first();
        $member = $group->groupRegistrations()->first();
        $second = DrawGroup::create(['draw_id' => $draw->id, 'name' => 'B']);
        DrawGroupRegistration::create(['draw_group_id' => $second->id, 'registration_id' => $member->registration_id, 'seed' => 1]);
        $this->postJson(route('backend.event-assistant.generation-preview', $event), ['draw_id' => $draw->id])->assertUnprocessable();
        $this->assertSame(0, $draw->drawFixtures()->count());
    }
}
