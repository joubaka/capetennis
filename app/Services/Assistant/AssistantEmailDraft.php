<?php

namespace App\Services\Assistant;

use App\Models\{Event, EventAssistantConversation, EventCommunicationBatch, User};
use App\Services\{CommunicationSender, EventCommunicationService};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\ValidationException;

final class AssistantEmailDraft
{
    public function audiences(Event $event): array
    {
        $choices = [['id' => 'all', 'name' => 'All event players’ recorded notification contacts'], ['id' => 'nominations', 'name' => 'Nominated players’ recorded notification contacts']];
        if (! $event->isTeam() && ! $event->isInterprovincialTrials()) {
            $choices[] = ['id' => 'registrations', 'name' => 'Registered players’ recorded notification contacts'];
            if ($event->isMasters()) $choices[] = ['id' => 'invitations', 'name' => 'Invited players’ recorded notification contacts'];
        }
        $choices[] = ['id' => 'direct', 'name' => 'One email address'];
        return $choices;
    }

    public function recognises(string $message, array $data): bool
    {
        return isset($data['email_draft']) || (bool) preg_match('/\b(?:email|e-mail)\b/i', $message);
    }

    public function prepare(string $message, array $data): array
    {
        $draft = ['subject' => (string) ($data['email_draft']['subject'] ?? ''), 'body' => (string) ($data['email_draft']['body'] ?? '')];
        if (empty($data['use_ai'])) {
            return ['draft' => $draft, 'notice' => 'Edit the email below, or select Use AI and describe the message or correction. Choose the audience explicitly, then review the exact recipients before sending. No email has been sent.'];
        }
        return ['draft' => app(AssistantProvider::class)->draftEmail($message, $draft), 'notice' => 'Email draft prepared. Check every fact and edit it below. Choose the audience explicitly, then review the exact recipients and message. No email has been sent.'];
    }

    public function preview(Event $event, User $actor, string $uuid, array $options, string $subject, string $body): EventCommunicationBatch
    {
        abort_unless($actor->hasRole('super-user') && config('event-assistant.enabled'), 403);
        Gate::forUser($actor)->authorize('event.manage', $event);
        return DB::transaction(function () use ($event, $actor, $uuid, $options, $subject, $body) {
            $conversation = EventAssistantConversation::where('uuid', $uuid)->where('event_id', $event->id)->where('user_id', $actor->id)->where('expires_at', '>', now())->lockForUpdate()->firstOrFail();
            $marker = $conversation->messages()->create(['role' => 'assistant', 'content' => 'Email prepared for recipient review. Sending requires approval on the review page.', 'metadata' => ['outcome' => 'email_review']]);
            $options = array_intersect_key($options, array_flip(['scope', 'filter', 'recipients', 'direct_email']));
            abort_unless(in_array($options['scope'] ?? '', array_column($this->audiences($event), 'id'), true), 422);
            $options['recipients'] = 'players';
            $options['filter'] = 'all';
            $options = array_merge($options, CommunicationSender::eventDefaults($event, $actor), ['assistant_conversation_id' => $conversation->id, 'assistant_review_revision' => $marker->id]);
            return app(EventCommunicationService::class)->preview($event, $actor, $options, $subject, $body);
        });
    }

    public function assertCurrentReview(EventCommunicationBatch $batch, User $actor): void
    {
        if (! isset($batch->options['assistant_conversation_id'])) return;
        abort_unless($actor->hasRole('super-user') && config('event-assistant.enabled'), 403);
        Gate::forUser($actor)->authorize('event.manage', $batch->event);
        $conversation = EventAssistantConversation::whereKey($batch->options['assistant_conversation_id'])->where('event_id', $batch->event_id)->where('user_id', $actor->id)->where('expires_at', '>', now())->lockForUpdate()->first();
        if (! $conversation || (int) $conversation->messages()->max('id') !== (int) ($batch->options['assistant_review_revision'] ?? 0)) {
            throw ValidationException::withMessages(['preview' => 'The assistant conversation or email draft changed or expired. Review a fresh recipient and message preview before sending.']);
        }
    }
}
