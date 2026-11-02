<?php

namespace Tests\Feature\Assistant;

use App\Models\{BulkEmailLog, CategoryEvent, CategoryEventRegistration, Event, EventAssistantConversation, EventCommunicationBatch, Player, Registration, User};
use App\Services\{CommunicationSender, EventCommunicationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Http, Mail};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantEmailDraftTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        config(['event-assistant.enabled' => true, 'event-assistant.mode' => 'local_first', 'services.gemini.api_key' => 'fake-draft-key', 'mail.from.address' => 'default@example.test']);
        Bus::fake();
        Mail::fake();
        Http::preventStrayRequests();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create(['email' => 'organiser@example.test'])->assignRole('super-user');
        $event = Event::factory()->create(['eventType' => DB::table('eventtypes')->insertGetId(['name' => 'Individual', 'type' => 1])]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $registration = Registration::factory()->create();
        $player = Player::factory()->create(['email' => 'primary@example.test', 'email_2' => 'parent@example.test', 'email_3' => 'PARENT@example.test', 'userId' => null]);
        $registration->players()->attach($player);
        CategoryEventRegistration::factory()->create(['registration_id' => $registration->id, 'category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id]);
        $this->actingAs($actor);
        return [$event, $actor, $player];
    }

    private function manualDraft(Event $event): string
    {
        return $this->postJson(route('backend.event-assistant.message', $event), ['message' => 'Draft an email about rain delays'])
            ->assertOk()->assertJsonPath('email_draft.subject', '')->assertJsonPath('email_draft.body', '')->json('conversation_uuid');
    }

    private function preview(Event $event, string $uuid, array $overrides = [])
    {
        return $this->post(route('backend.event-communications.preview', $event), $overrides + ['assistant_conversation_uuid' => $uuid, 'scope' => 'registrations', 'filter' => 'all', 'recipients' => 'players', 'subject' => 'Weather contingency', 'body' => "Dear Parents,\nUnder 13 may play until 14:00 on Sunday."]);
    }

    public function test_manual_editor_is_honest_and_preview_uses_exact_edited_message_contacts_and_sender_then_explicit_send_is_idempotent(): void
    {
        [$event, $actor] = $this->scenario();
        $uuid = $this->manualDraft($event);
        $this->assertDatabaseCount('event_communication_batches', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Http::assertNothingSent();
        $this->preview($event, $uuid)->assertOk()->assertSee('Nothing is sent until you approve')->assertSee('Under 13 may play until 14:00 on Sunday.');
        $batch = EventCommunicationBatch::sole();
        $this->assertSame(['parent@example.test', 'primary@example.test'], array_column($batch->recipients, 'email'));
        $sender = CommunicationSender::eventDefaults($event, $actor);
        foreach ($sender as $key => $value) $this->assertSame($value, $batch->options[$key]);
        foreach ($batch->recipients as $recipient) {
            $this->assertSame($batch->subject, $recipient['subject']);
            $this->assertSame(nl2br(e($batch->body)), $recipient['html']);
        }
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->postJson(route('backend.event-communications.send', $event), ['token' => $batch->token])->assertUnprocessable();
        $send = ['token' => $batch->token, 'confirm_send' => 1];
        $this->post(route('backend.event-communications.send', $event), $send)->assertRedirect();
        $this->assertDatabaseCount('bulk_email_logs', 2);
        $this->post(route('backend.event-communications.send', $event), $send)->assertRedirect();
        $this->assertDatabaseCount('bulk_email_logs', 2);
        $this->assertSame($sender['reply_to'], BulkEmailLog::first()->payload['reply_to']);
        Mail::assertNothingSent();
    }

    public function test_ai_drafts_and_revises_only_explicit_instruction_and_visible_draft_without_roster_or_history(): void
    {
        [$event] = $this->scenario();
        $output = ['subject' => 'Rain contingency', 'body' => 'Under 13 may play until 14:00 on Sunday.'];
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($output)]]]]]])]);
        $response = $this->postJson(route('backend.event-assistant.message', $event), ['message' => 'Write an email to parents: rain may delay Under 13 until 14:00 Sunday.', 'use_ai' => true])->assertOk()->assertJsonPath('email_draft', $output)->json();
        $edited = ['subject' => 'My edited subject', 'body' => 'Under 30 may play Sunday.'];
        $this->postJson(route('backend.event-assistant.message', $event), ['conversation_uuid' => $response['conversation_uuid'], 'message' => 'I meant Under 13, not Under 30; latest finish 14:00 Sunday.', 'email_draft' => $edited, 'use_ai' => true])->assertOk()->assertJsonPath('email_draft', $output);
        Http::assertSentCount(2);
        $sent = Http::recorded()->last()[0]->data();
        $prompt = $sent['contents'][0]['parts'][0]['text'];
        $this->assertStringContainsString('My edited subject', $prompt);
        $this->assertStringContainsString('I meant Under 13', $prompt);
        $this->assertStringNotContainsString('Write an email to parents', $prompt);
        foreach (['primary@example.test', 'parent@example.test', 'organiser@example.test', $event->name] as $private) $this->assertStringNotContainsString($private, $prompt);
        $this->assertDatabaseCount('event_communication_batches', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Mail::assertNothingSent();
    }

    public function test_revision_or_second_preview_invalidates_previous_review_without_queueing(): void
    {
        [$event] = $this->scenario();
        $uuid = $this->manualDraft($event);
        $this->preview($event, $uuid)->assertOk();
        $first = EventCommunicationBatch::sole();
        $this->postJson(route('backend.event-assistant.message', $event), ['conversation_uuid' => $uuid, 'message' => 'Change to Under 13', 'email_draft' => ['subject' => $first->subject, 'body' => $first->body]])->assertOk();
        $this->postJson(route('backend.event-communications.send', $event), ['token' => $first->token, 'confirm_send' => 1])->assertUnprocessable()->assertJsonValidationErrors('preview');
        $this->preview($event, $uuid, ['body' => 'Revised body'])->assertOk();
        $second = EventCommunicationBatch::latest('id')->first();
        $this->preview($event, $uuid, ['body' => 'Latest body'])->assertOk();
        $this->postJson(route('backend.event-communications.send', $event), ['token' => $second->token, 'confirm_send' => 1])->assertUnprocessable()->assertJsonValidationErrors('preview');
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_foreign_event_actor_expired_conversation_and_pilot_revocation_fail_closed(): void
    {
        [$event, $actor] = $this->scenario();
        $uuid = $this->manualDraft($event);
        $other = Event::factory()->create();
        $this->preview($other, $uuid)->assertNotFound();
        $otherActor = User::factory()->create()->assignRole('super-user');
        $this->actingAs($otherActor);
        $this->preview($event, $uuid)->assertNotFound();
        $this->actingAs($actor);
        $this->preview($event, $uuid)->assertOk();
        $batch = EventCommunicationBatch::sole();
        EventAssistantConversation::where('uuid', $uuid)->update(['expires_at' => now()->subMinute()]);
        $this->postJson(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertUnprocessable();
        $actor->removeRole('super-user');
        $this->postJson(route('backend.event-assistant.message', $event), ['message' => 'Draft email'])->assertForbidden();
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Http::assertNothingSent();
    }

    public function test_audience_selection_is_required_and_changed_recipient_contacts_reject_send(): void
    {
        [$event, , $player] = $this->scenario();
        $uuid = $this->manualDraft($event);
        $this->preview($event, $uuid, ['scope' => ''])->assertSessionHasErrors('scope');
        $this->assertDatabaseCount('event_communication_batches', 0);
        $this->preview($event, $uuid)->assertOk();
        $batch = EventCommunicationBatch::sole();
        $player->update(['email_2' => 'changed@example.test']);
        $this->postJson(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertUnprocessable()->assertJsonValidationErrors('preview');
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_provider_failure_invalid_output_and_local_only_mode_leave_draft_unsent(): void
    {
        [$event] = $this->scenario();
        $uuid = $this->manualDraft($event);
        $data = ['message' => 'Revise email', 'conversation_uuid' => $uuid, 'email_draft' => ['subject' => 'Keep this', 'body' => 'Keep this body'], 'use_ai' => true];
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['private' => 'provider-secret'], 500)]);
        $this->postJson(route('backend.event-assistant.message', $event), $data)->assertStatus(503)->assertDontSee('provider-secret');
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"action":"send","subject":"Bad","body":"Hi"}']]]]]])]);
        $this->postJson(route('backend.event-assistant.message', $event), $data)->assertStatus(503);
        config(['event-assistant.mode' => 'local_only']);
        Http::fake();
        $this->postJson(route('backend.event-assistant.message', $event), $data)->assertStatus(503);
        Http::assertNothingSent();
        $this->postJson(route('backend.event-assistant.message', $event), array_replace($data, ['use_ai' => false]))->assertOk()->assertJsonPath('email_draft.subject', 'Keep this')->assertJsonPath('email_draft.body', 'Keep this body');
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_review_rejects_deleted_conversation_or_revoked_pilot_role_before_sending(): void
    {
        [$event, $actor] = $this->scenario();
        $uuid = $this->manualDraft($event);
        $this->preview($event, $uuid)->assertOk();
        $batch = EventCommunicationBatch::sole();
        $actor->removeRole('super-user');
        $this->postJson(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertForbidden();
        $actor->assignRole('super-user');
        EventAssistantConversation::where('uuid', $uuid)->delete();
        $this->postJson(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertUnprocessable()->assertJsonValidationErrors('preview');
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Mail::assertNothingSent();
    }
}
