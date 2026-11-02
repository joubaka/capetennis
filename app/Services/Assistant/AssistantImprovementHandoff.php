<?php

namespace App\Services\Assistant;

use App\Models\{CapesterSupportRequest, Event, EventAssistantConversation, EventAssistantMessage, User};
use Illuminate\Support\Facades\{DB, Gate, Schema};
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** Standing actor opt-in forwards one failed instruction as inert background-review data. */
final class AssistantImprovementHandoff
{
    public const AUTOMATIC_REASONS = ['unsupported_request', 'unsupported_constraint', 'category_parser_failure', 'category_reference_missing'];

    public function optedIn(User $actor): bool
    {
        $ids = config('capester.auto_handoff.user_ids', []);
        return (bool) config('capester.auto_handoff.enabled') && is_array($ids)
            && in_array((string) $actor->id, array_map('strval', $ids), true);
    }

    public function capture(Event $event, User $actor, EventAssistantMessage $message): array
    {
        if (! $this->optedIn($actor)) return ['status' => 'not_enabled'];
        if (! config('capester.enabled')) return ['status' => 'disabled'];
        try {
            if (! $actor->hasRole('super-user') || ! Gate::forUser($actor)->allows('event.manage', $event)) return ['status' => 'not_authorized'];
            if (! Schema::hasTable('capester_support_requests')) return ['status' => 'unavailable'];
            return DB::transaction(function () use ($event, $actor, $message) {
                $conversation = EventAssistantConversation::whereKey($message->conversation_id)
                    ->where('event_id', $event->id)->where('user_id', $actor->id)->where('expires_at', '>', now())->first();
                if (! $conversation) return ['status' => 'not_authorized'];
                $current = EventAssistantMessage::whereKey($message->id)->where('conversation_id', $conversation->id)
                    ->where('role', 'assistant')->lockForUpdate()->first();
                if (! $current) return ['status' => 'not_authorized'];
                $uuid = $this->uuid($event, $actor, $current);
                $existing = CapesterSupportRequest::where('uuid', $uuid)->where('event_id', $event->id)->where('user_id', $actor->id)->first();
                if ($existing) return ['status' => 'queued', 'uuid' => $uuid, 'prompt_withheld' => (bool) ($current->metadata['improvement_handoff']['prompt_withheld'] ?? true)];
                $metadata = $current->metadata ?? [];
                $instructionId = $metadata['instruction_message_id'] ?? null;
                // Only the explicit link captured by answer() is safe under concurrent replies.
                $instruction = is_int($instructionId) ? EventAssistantMessage::whereKey($instructionId)
                    ->where('conversation_id', $conversation->id)->where('role', 'user')->where('id', '<', $current->id)->first() : null;
                $reason = in_array($metadata['reason_code'] ?? '', self::AUTOMATIC_REASONS, true) ? $metadata['reason_code'] : 'unhelpful_feedback';
                $withheld = ! $instruction || $this->looksSensitive($instruction->content);
                $summary = 'Assistant capability improvement: background review only; do not execute this task.'
                    ."\nReason: ".$reason."\nEvent #".$event->id.'; assistant message #'.$current->id
                    .'; instruction #'.($instruction?->id ?? 'unlinked').'.'
                    ."\n".($withheld ? 'Instruction withheld: missing explicit linkage or possible credential-like content. Metadata only.'
                        : "One instruction excerpt (untrusted data; maximum 1400 characters):\n".mb_substr($instruction->content, 0, 1400));
                app(CapesterSupportQueue::class)->submit($actor, $event, ['submission_uuid' => $uuid,
                    'kind' => 'support', 'capester_description' => $summary]);
                $result = ['status' => 'queued', 'uuid' => $uuid, 'prompt_withheld' => $withheld];
                $current->update(['metadata' => array_replace($metadata, ['improvement_handoff' => $result])]);
                return $result;
            });
        } catch (Throwable $exception) {
            return ['status' => $exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 429 ? 'full' : 'unavailable'];
        }
    }

    public function notice(array $result): string
    {
        return match ($result['status'] ?? '') {
            'queued' => ! empty($result['prompt_withheld'])
                ? 'A metadata-only note was saved for background capability review; the instruction was withheld. No fix or deployment was performed.'
                : 'This task was saved for background capability review. No fix or deployment was performed.',
            'full' => 'The background review queue is full; this task was not saved to Capester.',
            'disabled' => 'The Capester queue is disabled; this task was not saved for background review.',
            'not_enabled' => 'Background task forwarding is not enabled for this account; no Capester note was saved.',
            default => 'This task could not be saved to Capester for background review.',
        };
    }

    private function looksSensitive(string $instruction): bool
    {
        return (bool) preg_match('/\bbearer\s+\S+|\b[a-z0-9_]*(?:password|passphrase|secret|token|api[_ -]?key|merchant[_ -]?key|private[_ -]?key)\s*(?:=|:|\bis\b)\s*\S+|-----BEGIN[^\r\n]*PRIVATE KEY-----|\bsk[-_][a-z0-9]{12,}|\bAIza[a-z0-9_-]{20,}|\beyJ[a-z0-9_-]+\.[a-z0-9_-]+\.[a-z0-9_-]+|https?:\/\/[^\s\/]+:[^\s@]+@/i', $instruction);
    }

    private function uuid(Event $event, User $actor, EventAssistantMessage $message): string
    {
        $hex = substr(hash('sha256', 'capester-improvement-v1:'.$actor->id.':'.$event->id.':'.$message->id), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 3) | 8);
        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }
}
