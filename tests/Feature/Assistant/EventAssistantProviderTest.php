<?php

namespace Tests\Feature\Assistant;

use App\Models\{Draw, Event, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Http};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventAssistantProviderTest extends TestCase
{
    public function test_unsupported_constraints_are_clarified_before_even_opted_in_provider_calls(): void
    {
        $event = $this->scenario();
        Http::preventStrayRequests();
        $this->postJson(route('backend.event-assistant.message', $event), ['message' => 'Keep court 2 free until 10', 'use_ai' => true])
            ->assertOk()->assertJsonPath('proposal', null)->assertJsonStructure(['clarification']);
        Http::assertNothingSent();
        $this->assertDatabaseCount('event_assistant_proposals', 0);
    }

    use RefreshDatabase;

    private function scenario(): Event
    {
        config(['event-assistant.enabled' => true, 'event-assistant.mode' => 'local_first', 'event-assistant.transcription.provider' => 'gemini', 'services.gemini.api_key' => 'test-only-key']);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        $event = Event::factory()->create(['name' => 'Provider test event', 'start_date' => '2026-11-07', 'end_date' => '2026-11-07']);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $this->actingAs($actor);
        return $event;
    }

    public function test_malformed_provider_responses_are_controlled_failures_with_no_actions(): void
    {
        $event = $this->scenario();
        $bodies = ['not json', json_encode(['candidates' => [['content' => ['parts' => [['text' => '{"action":"schedule","options":"invalid"}']]]]]])];
        $sequence = Http::sequence();
        foreach ($bodies as $body) $sequence->push($body, 200);
        Http::fake(['*' => $sequence]);
        foreach ($bodies as $body) {
            $this->postJson(route('backend.event-assistant.message', $event), ['message' => 'Schedule Saturday', 'use_ai' => true])->assertStatus(503);
        }
        $this->assertDatabaseCount('event_assistant_proposals', 0);
        $this->assertDatabaseCount('fixtures', 0);
    }

    public function test_model_identifiers_are_resolved_against_event_and_cannot_execute_arbitrary_actions(): void
    {
        $event = $this->scenario();
        $foreign = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['action' => 'schedule', 'options' => ['date' => '2026-11-07', 'draw_ids' => [$foreign->id]]])]]]]]])]);
        $this->postJson(route('backend.event-assistant.message', $event), ['message' => 'Schedule a draw', 'use_ai' => true])->assertUnprocessable();
        $this->assertDatabaseCount('event_assistant_proposals', 0);
        $this->assertDatabaseCount('order_of_plays', 0);
    }

    public function test_ai_opt_in_is_per_request_and_transcription_does_not_submit_or_persist_audio(): void
    {
        $event = $this->scenario();
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Schedule Saturday at eight']]]]]])]);
        $audio = UploadedFile::fake()->create('question.wav', 1, 'audio/wav');
        $this->post(route('backend.event-assistant.transcribe', $event), ['audio' => $audio, 'use_ai' => true], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('transcript', 'Schedule Saturday at eight');
        $this->assertDatabaseCount('event_assistant_conversations', 0);
        $this->assertDatabaseCount('event_assistant_messages', 0);
        $this->postJson(route('backend.event-assistant.message', $event), ['message' => 'What can you do?'])->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key') && ! str_contains($request->url(), 'key='));
    }

    public function test_voice_needs_separate_explicit_consent_before_provider_call(): void
    {
        $event = $this->scenario();
        Http::preventStrayRequests();
        $this->post(route('backend.event-assistant.transcribe', $event), ['audio' => UploadedFile::fake()->create('question.wav', 1, 'audio/wav')], ['Accept' => 'application/json'])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_voice_uses_the_full_model_while_text_interpretation_keeps_the_fast_model(): void
    {
        $event = $this->scenario();
        config(['services.gemini.model' => 'voice-full-test', 'services.gemini.model_fast' => 'text-fast-test']);
        Http::preventStrayRequests();
        Http::fake([
            '*voice-full-test:generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Schedule Saturday at eight']]]]]]),
            '*text-fast-test:generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"action":"help"}']]]]]]),
        ]);
        $this->post(route('backend.event-assistant.transcribe', $event), [
            'audio' => UploadedFile::fake()->create('question.wav', 1, 'audio/wav'), 'use_ai' => true,
        ], ['Accept' => 'application/json'])->assertOk();
        $this->postJson(route('backend.event-assistant.message', $event), ['message' => 'What can you do?', 'use_ai' => true])->assertOk();
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/voice-full-test:generateContent')
            && $request->hasHeader('x-goog-api-key') && ! str_contains($request->url(), 'key='));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/text-fast-test:generateContent'));
        $this->assertDatabaseCount('event_assistant_proposals', 0);
    }

    public function test_provider_status_errors_are_actionable_and_never_echo_provider_bodies(): void
    {
        $event = $this->scenario();
        $status = 401;
        Http::fake(function () use (&$status) {
            return Http::response(['error' => ['message' => 'private-provider-body-test']], $status);
        });
        foreach ([401 => 'API key or permissions', 403 => 'API key or permissions', 404 => 'model is unavailable', 429 => 'request or usage limit', 400 => 'shorter prompt', 503 => 'temporarily unavailable'] as $status => $text) {
            $this->postJson(route('backend.event-assistant.message', $event), ['message' => 'Schedule Saturday', 'use_ai' => true])
                ->assertStatus(503)->assertSee($text)->assertDontSee('private-provider-body-test');
        }
        $this->assertDatabaseCount('event_assistant_proposals', 0);
        $this->assertDatabaseCount('fixtures', 0);
        $this->assertDatabaseCount('order_of_plays', 0);
    }

    public function test_unsupported_provider_options_are_not_silently_discarded_or_applied(): void
    {
        $event = $this->scenario();
        $cases = [['reschedule_existing' => true], ['court closure'], ['date' => '2026-11-07', 'direct_sql' => 'untrusted']];
        $sequence = Http::sequence();
        foreach ($cases as $options) $sequence->push(['candidates' => [['content' => ['parts' => [['text' => json_encode(['action' => 'schedule', 'options' => $options])]]]]]]);
        Http::fake(['*' => $sequence]);
        foreach ($cases as $options) {
            $this->postJson(route('backend.event-assistant.message', $event), ['message' => 'Schedule Saturday', 'use_ai' => true])
                ->assertStatus(503)->assertSee('unsupported scheduling details');
        }
        $this->assertDatabaseCount('event_assistant_proposals', 0);
        $this->assertDatabaseCount('order_of_plays', 0);
    }

    public function test_whitespace_credentials_do_not_enable_ai_or_voice_capabilities(): void
    {
        $event = $this->scenario();
        config(['services.gemini.api_key' => '   ']);
        Http::preventStrayRequests();
        $this->getJson(route('backend.event-assistant.context', $event))->assertOk()
            ->assertJsonPath('capabilities.ai_available', false)->assertJsonPath('capabilities.voice_available', false);
        $this->postJson(route('backend.event-assistant.message', $event), ['message' => 'Schedule Saturday', 'use_ai' => true])->assertStatus(503);
        Http::assertNothingSent();
    }
}
