<?php

namespace Tests\Feature\Assistant;

use App\Models\{CapesterSupportRequest, Event, EventAssistantConversation, EventAssistantMessage, User};
use App\Services\Assistant\{AssistantImprovementHandoff, CapesterSupportQueue};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Schema};
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantImprovementHandoffTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        $event = Event::factory()->create();
        config(['event-assistant.enabled' => true, 'capester.enabled' => true,
            'capester.auto_handoff.enabled' => true, 'capester.auto_handoff.user_ids' => [(string) $actor->id]]);
        Http::preventStrayRequests();
        $this->actingAs($actor);
        return [$actor, $event];
    }

    private function answer(Event $event, string $question, array $extra = []): array
    {
        return $this->postJson(route('backend.event-assistant.message', $event), ['message' => $question] + $extra)->assertOk()->json();
    }

    private function feedback(Event $event, int $id, array $extra = [])
    {
        return $this->postJson(route('backend.event-assistant.feedback', $event), ['message_id' => $id, 'rating' => 'unhelpful', 'reason' => 'understanding'] + $extra);
    }

    public function test_unsupported_instruction_is_saved_once_and_feedback_reuses_stable_payload_uuid(): void
    {
        [, $event] = $this->scenario();
        $question = 'Translate the tournament guide into a fictional language.';
        $result = $this->answer($event, $question);
        $this->assertSame('queued', $result['improvement_handoff']['status']);
        $this->assertStringContainsString('saved for background', $result['message']['content']);
        $this->assertStringContainsString('has not been performed', $result['message']['content']);
        $item = CapesterSupportRequest::sole();
        $summary = $item->capester_description;
        $this->assertStringContainsString($question, $summary);
        $this->assertSame($result['improvement_handoff']['uuid'], $item->uuid);
        foreach ([1, 2] as $_) {
            $this->feedback($event, $result['message']['id'], ['comment' => 'DO_NOT_FORWARD_FEEDBACK_COMMENT'])->assertOk()->assertJsonPath('improvement_handoff.uuid', $item->uuid);
        }
        $this->assertDatabaseCount('capester_support_requests', 1);
        $this->assertSame($summary, $item->fresh()->capester_description);
        $this->assertStringNotContainsString('DO_NOT_FORWARD_FEEDBACK_COMMENT', $summary);
        $this->assertSame($item->uuid, EventAssistantMessage::findOrFail($result['message']['id'])->metadata['improvement_handoff']['uuid']);
        Http::assertNothingSent();
    }

    public function test_current_instruction_only_excludes_previous_messages_email_draft_and_feedback_comment(): void
    {
        [, $event] = $this->scenario();
        $first = $this->answer($event, 'Draft an email about the event.', ['email_draft' => ['subject' => 'PRIVATE_SUBJECT', 'body' => 'PRIVATE_CURRENT_DRAFT']]);
        $this->assertDatabaseCount('capester_support_requests', 0);
        $question = 'Translate the guide into a fictional language.';
        $second = $this->answer($event, $question, ['conversation_uuid' => $first['conversation_uuid']]);
        $item = CapesterSupportRequest::sole();
        $this->assertStringContainsString($question, $item->capester_description);
        foreach (['PRIVATE_SUBJECT', 'PRIVATE_CURRENT_DRAFT', 'Draft an email about the event.'] as $private) $this->assertStringNotContainsString($private, $item->capester_description);
        $this->feedback($event, $first['message']['id'], ['comment' => 'PRIVATE_COMMENT'])->assertOk()->assertJsonPath('improvement_handoff.status', 'queued');
        $emailNote = CapesterSupportRequest::where('uuid', '!=', $item->uuid)->sole();
        $this->assertStringContainsString('Draft an email about the event.', $emailNote->capester_description);
        foreach (['PRIVATE_CURRENT_DRAFT', 'PRIVATE_SUBJECT', 'PRIVATE_COMMENT', $question] as $private) $this->assertStringNotContainsString($private, $emailNote->capester_description);
        Http::assertNothingSent();
    }

    public function test_unsupported_constraints_and_category_grammar_are_captured_but_normal_clarifications_and_denied_actions_are_not(): void
    {
        [, $event] = $this->scenario();
        foreach (['Schedule this event', 'Create a draw', 'Schedule Saturday', 'Delete all draws without changing fees', 'Refund the payer'] as $question) {
            $this->answer($event, $question);
            $this->assertDatabaseCount('capester_support_requests', 0);
        }
        Event::factory()->create(['name' => '2025 Spur Panarottis Leg 1']);
        Event::factory()->create(['name' => '2026 Spur Panarottis Leg 1']);
        $ambiguity = $this->answer($event, 'Copy categories from Spur Panarottis Leg One to Spur Panarottis Leg Two');
        $this->assertNull($ambiguity['improvement_handoff']);
        $this->assertDatabaseCount('capester_support_requests', 0);
        $constraint = $this->answer($event, 'Keep court 2 free until 10');
        $this->assertSame('queued', $constraint['improvement_handoff']['status']);
        $grammar = $this->answer($event, 'Copy categories somewhere unusual');
        $this->assertSame('queued', $grammar['improvement_handoff']['status']);
        $this->assertDatabaseCount('capester_support_requests', 2);
        $this->assertStringContainsString('category_parser_failure', CapesterSupportRequest::latest('id')->first()->capester_description);
        $typo = $this->answer($event, 'Copy categories from Spur Panaratis leg one to Spur Panaratis leg two as well');
        $this->assertSame('queued', $typo['improvement_handoff']['status']);
        $this->assertStringContainsString('category_reference_missing', CapesterSupportRequest::latest('id')->first()->capester_description);
    }

    public function test_credentials_are_withheld_as_metadata_only_instead_of_claiming_complete_redaction(): void
    {
        [, $event] = $this->scenario();
        foreach (['GEMINI_API_KEY=fictional-value', 'PAYFAST_MERCHANT_KEY=fictional-value', 'password is fictional-value',
            'Authorization: Bearer fictional-value', 'DB_PASSWORD=fictional-value', 'api key: fictional-value'] as $secret) {
            $result = $this->answer($event, 'Investigate configuration '.$secret);
            $this->assertSame('queued', $result['improvement_handoff']['status']);
            $this->assertTrue($result['improvement_handoff']['prompt_withheld']);
            $this->assertStringContainsString('metadata-only', $result['message']['content']);
            $description = CapesterSupportRequest::where('uuid', $result['improvement_handoff']['uuid'])->sole()->capester_description;
            $this->assertStringNotContainsString('fictional-value', $description);
            $this->assertStringContainsString('Instruction withheld', $description);
        }
        Http::assertNothingSent();
    }

    public function test_opt_in_requires_both_switch_and_exact_actor_allowlist_and_queue_enabled(): void
    {
        [$actor, $event] = $this->scenario();
        config(['capester.auto_handoff.enabled' => false]);
        $off = $this->answer($event, 'Translate a tournament guide.');
        $this->assertSame('not_enabled', $off['improvement_handoff']['status']);
        $this->assertStringNotContainsString('saved for background', $off['message']['content']);
        config(['capester.auto_handoff.enabled' => true, 'capester.auto_handoff.user_ids' => [(string) ($actor->id + 1)]]);
        $this->answer($event, 'Translate a tournament guide.');
        $this->assertDatabaseCount('capester_support_requests', 0);
        config(['capester.auto_handoff.user_ids' => [(string) $actor->id], 'capester.enabled' => false]);
        $disabled = $this->answer($event, 'Translate a tournament guide.');
        $this->assertSame('disabled', $disabled['improvement_handoff']['status']);
        $this->assertStringContainsString('was not saved', $disabled['message']['content']);
        $this->feedback($event, $disabled['message']['id'])->assertOk()->assertJsonPath('improvement_handoff.status', 'disabled');
        $this->assertDatabaseCount('capester_support_requests', 0);
    }

    public function test_full_and_unavailable_queue_do_not_break_replies_or_feedback_or_claim_success(): void
    {
        [$actor, $event] = $this->scenario();
        foreach (range(1, 20) as $_) app(CapesterSupportQueue::class)->submit($actor, $event, ['submission_uuid' => (string) Str::uuid(), 'kind' => 'support', 'capester_description' => 'Fictional existing queue item.']);
        $full = $this->answer($event, 'Translate a tournament guide.');
        $this->assertSame('full', $full['improvement_handoff']['status']);
        $this->assertStringContainsString('was not saved', $full['message']['content']);
        $this->feedback($event, $full['message']['id'])->assertOk()->assertJsonPath('improvement_handoff.status', 'full');
        $this->assertDatabaseCount('capester_support_requests', 20);
        Schema::shouldReceive('hasTable')->andReturnUsing(fn ($table) => $table !== 'capester_support_requests');
        $unavailable = $this->answer($event, 'Translate a tournament guide.');
        $this->assertSame('unavailable', $unavailable['improvement_handoff']['status']);
        $this->assertStringContainsString('could not be saved', $unavailable['message']['content']);
        $this->feedback($event, $unavailable['message']['id'])->assertOk()->assertJsonPath('improvement_handoff.status', 'unavailable');
    }

    public function test_feedback_and_handoff_recheck_actor_event_and_explicit_instruction_linkage(): void
    {
        [$actor, $event] = $this->scenario();
        $response = $this->answer($event, 'Schedule this event');
        $other = User::factory()->create()->assignRole('super-user');
        config(['capester.auto_handoff.user_ids' => [(string) $actor->id, (string) $other->id]]);
        $this->actingAs($other);
        $this->feedback($event, $response['message']['id'])->assertNotFound();
        $message = EventAssistantMessage::findOrFail($response['message']['id']);
        $service = app(AssistantImprovementHandoff::class);
        $this->assertSame('not_authorized', $service->capture($event, $other, $message)['status']);
        $otherEvent = Event::factory()->create();
        $this->assertSame('not_authorized', $service->capture($otherEvent, $actor, $message)['status']);
        $this->assertDatabaseCount('capester_support_requests', 0);
        // Legacy/unlinked replies must not infer "latest user" or copy a transcript.
        $legacy = EventAssistantMessage::create(['conversation_id' => $message->conversation_id, 'role' => 'assistant', 'content' => 'Legacy reply', 'metadata' => []]);
        $result = $service->capture($event, $actor, $legacy);
        $this->assertTrue($result['prompt_withheld']);
        $this->assertStringContainsString('instruction #unlinked', CapesterSupportRequest::sole()->capester_description);
    }

    public function test_instruction_excerpt_is_bounded_but_secret_scan_includes_its_withheld_tail(): void
    {
        [, $event] = $this->scenario();
        $question = 'Translate '.str_repeat('x', 1750).'EXCLUDED_TAIL';
        $result = $this->answer($event, $question);
        $description = CapesterSupportRequest::where('uuid', $result['improvement_handoff']['uuid'])->sole()->capester_description;
        $this->assertLessThanOrEqual(2000, mb_strlen($description));
        $this->assertStringContainsString(mb_substr($question, 0, 1400), $description);
        $this->assertStringNotContainsString('EXCLUDED_TAIL', $description);
        $withheld = $this->answer($event, 'Translate '.str_repeat('x', 1750).' GEMINI_API_KEY=fictional-tail');
        $this->assertTrue($withheld['improvement_handoff']['prompt_withheld']);
        $description = CapesterSupportRequest::where('uuid', $withheld['improvement_handoff']['uuid'])->sole()->capester_description;
        $this->assertStringNotContainsString('fictional-tail', $description);
        $this->assertStringNotContainsString(str_repeat('x', 50), $description);
    }
}
