<?php

namespace Tests\Feature\Assistant;

use App\Models\{Draw, Event, Player, Registration, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Http};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventAssistantWhisperTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): Event
    {
        config(['event-assistant.enabled' => true, 'event-assistant.mode' => 'local_only',
            'event-assistant.transcription.provider' => 'whisper',
            'event-assistant.transcription.endpoint' => 'http://127.0.0.1:8178/v1/audio/transcriptions',
            'services.gemini.api_key' => '']);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        $event = Event::factory()->create();
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $this->actingAs($actor);

        return $event;
    }

    private function recording(Event $event, array $extra = [])
    {
        return $this->post(route('backend.event-assistant.transcribe', $event), array_replace([
            'audio' => UploadedFile::fake()->create('question.wav', 1, 'audio/wav'), 'transcription_consent' => true,
        ], $extra), ['Accept' => 'application/json']);
    }

    public function test_local_voice_works_without_gemini_and_only_sends_event_names_to_loopback(): void
    {
        $event = $this->scenario();
        $local = Player::factory()->create(['name' => 'Fictional', 'surname' => 'Joubert']);
        $foreign = Player::factory()->create(['name' => 'Foreign', 'surname' => 'Hidden']);
        foreach ([[$event, $local], [Event::factory()->create(), $foreign]] as [$owner, $player]) {
            $registration = Registration::factory()->create();
            $registration->players()->attach($player);
            $registration->draws()->attach(Draw::factory()->create(['event_id' => $owner->id]));
        }
        Http::preventStrayRequests();
        Http::fake(['http://127.0.0.1:8178/*' => Http::response(['text' => '  Schedule Fictional Joubert at nine.  '])]);
        $this->getJson(route('backend.event-assistant.context', $event))->assertOk()
            ->assertJsonPath('capabilities.ai_available', false)->assertJsonPath('capabilities.voice_available', true)
            ->assertJsonPath('capabilities.voice_local', true);
        $this->recording($event)->assertOk()->assertJsonPath('transcript', 'Schedule Fictional Joubert at nine.');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8178/v1/audio/transcriptions'
            && $request->isMultipart() && $request->hasFile('file')
            && str_contains($request->body(), 'Fictional Joubert')
            && ! str_contains($request->body(), 'Foreign Hidden')
            && ! str_contains($request->body(), $local->email)
            && ! $request->hasHeader('x-goog-api-key'));
        $this->assertDatabaseCount('event_assistant_messages', 0);
        $this->assertDatabaseCount('event_assistant_conversations', 0);
        $this->assertDatabaseCount('event_assistant_proposals', 0);
    }

    public function test_local_voice_requires_its_own_consent_and_valid_audio(): void
    {
        $event = $this->scenario();
        Http::preventStrayRequests();
        $this->recording($event, ['transcription_consent' => false, 'use_ai' => true])->assertUnprocessable();
        $this->recording($event, ['audio' => UploadedFile::fake()->create('large.wav', 5121, 'audio/wav')])->assertUnprocessable();
        $this->recording($event, ['audio' => UploadedFile::fake()->create('document.pdf', 1, 'application/pdf')])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_local_errors_and_empty_results_are_controlled_without_cloud_fallback(): void
    {
        $event = $this->scenario();
        $sequence = Http::sequence()->push(['error' => 'private detail'], 500)->push(['text' => '   '])
            ->push(['text' => ['bad']])->push('not json');
        Http::fake(['http://127.0.0.1:8178/*' => $sequence]);
        foreach (range(1, 4) as $attempt) $this->recording($event)->assertStatus(503)->assertDontSee('private detail');
        Http::assertSentCount(4);
        $this->assertDatabaseCount('event_assistant_messages', 0);
    }

    public function test_connection_failures_do_not_use_a_cloud_fallback(): void
    {
        $event = $this->scenario();
        Http::fake(['http://127.0.0.1:8178/*' => Http::failedConnection()]);
        $this->recording($event)->assertStatus(503)->assertSee('Local Whisper could not connect');
        Http::assertSentCount(1);
    }

    public function test_non_loopback_endpoints_and_production_cannot_enable_local_trial(): void
    {
        $event = $this->scenario();
        Http::preventStrayRequests();
        config(['event-assistant.transcription.endpoint' => 'http://example.com/v1/audio/transcriptions']);
        $this->recording($event)->assertStatus(503);
        config(['event-assistant.transcription.endpoint' => 'http://127.0.0.1:8178/v1/audio/transcriptions']);
        $this->app->instance('env', 'production');
        $this->assertFalse(app(\App\Services\Assistant\AssistantTranscriptionProvider::class)->available());
        Http::assertNothingSent();
    }

    public function test_non_super_users_cannot_transcribe_or_disclose_event_names(): void
    {
        $event = $this->scenario();
        $this->actingAs(User::factory()->create());
        Http::preventStrayRequests();
        $this->recording($event)->assertForbidden();
        Http::assertNothingSent();
    }
}
