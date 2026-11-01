<?php

namespace App\Services\Assistant;

use App\Models\{Draw, DrawAuditLog, DrawType, Event, EventAssistantConversation, EventAssistantFeedback, EventAssistantMessage, EventAssistantPreference, EventAssistantProposal, User, Venue};
use App\Services\Scheduling\{EventVenueScheduleService, ScheduleProgramme, SchedulePublicationService};
use Carbon\Carbon;
use Illuminate\Support\Facades\{DB, Gate, Schema, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SportStack\Assistant\{FeedbackClassifier, ProposalFingerprint};

final class EventAssistantService
{
    public function __construct(private readonly EventVenueScheduleService $scheduler, private readonly SchedulePublicationService $publication) {}

    public function context(Event $event, User $actor): array
    {
        Gate::forUser($actor)->authorize('event.manage', $event);
        $draws = $event->draws()->with('venues')->orderBy('id')->limit(201)->get();
        abort_if($draws->count() > 200, 422, 'Choose a smaller event workspace before using the assistant.');
        $venueIds = $event->venues()->pluck('venues.id')->merge($draws->flatMap(fn ($draw) => $draw->venues->pluck('id')))->unique();
        $venues = Venue::whereIn('id', $venueIds)->orderBy('name')->limit(201)->get();
        abort_if($venues->count() > 200, 422, 'This event exceeds the assistant venue limit.');
        $ready = Schema::hasTable('event_assistant_conversations');
        $preferences = $ready ? EventAssistantPreference::where('event_id', $event->id)->where('user_id', $actor->id)->value('preferences') ?? [] : [];
        $start = $event->start_date ? Carbon::parse($event->start_date)->startOfDay() : null;
        $end = $event->end_date ? Carbon::parse($event->end_date)->startOfDay() : $start;
        $dates = [];
        if ($start && $end && $end->gte($start) && $start->diffInDays($end) <= 31) {
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) $dates[] = $date->toDateString();
        }
        return [
            'available' => $ready && (bool) config('event-assistant.enabled'),
            'event' => ['id' => $event->id, 'name' => $event->name],
            'dates' => $dates, 'timezone' => config('app.timezone'),
            'draws' => $draws->map(fn ($draw) => ['id' => (int) $draw->id, 'name' => $draw->drawName, 'locked' => (bool) $draw->locked, 'published' => (bool) $draw->published, 'venue_ids' => $draw->venues->pluck('id')->map(fn ($id) => (int) $id)->all()])->all(),
            'venues' => $venues->map(fn ($venue) => ['id' => (int) $venue->id, 'name' => $venue->name])->all(),
            'court_labels' => $this->scheduler->fullDayCourtLabels($event),
            'defaults' => array_replace(['start_time' => '08:00', 'end_time' => '17:00', 'duration' => 60, 'court_gap' => 5, 'player_rest' => 60], $preferences),
            'preferences' => $preferences,
            'capabilities' => ['ai_available' => app(AssistantProvider::class)->available(), 'voice_available' => app(AssistantProvider::class)->available(), 'schedule' => true, 'publication' => true, 'draw_draft' => Gate::forUser($actor)->allows('individual-draw.create', $event), 'draw_generation' => 'prepared_initial_round_robin'],
        ];
    }

    public function conversation(Event $event, User $actor, ?string $uuid = null): EventAssistantConversation
    {
        Gate::forUser($actor)->authorize('event.manage', $event);
        abort_unless(config('event-assistant.enabled') && Schema::hasTable('event_assistant_conversations'), 503, 'The assistant needs its reviewed local database setup.');
        if ($uuid) {
            return EventAssistantConversation::where('uuid', $uuid)->where('event_id', $event->id)->where('user_id', $actor->id)->where('expires_at', '>', now())->firstOrFail();
        }
        // Retention is bounded and removes child messages/proposals through FK cascades.
        EventAssistantConversation::where('event_id', $event->id)->where('user_id', $actor->id)->where('expires_at', '<=', now())->limit(100)->get()->each->delete();
        return EventAssistantConversation::create(['uuid' => (string) Str::uuid(), 'event_id' => $event->id, 'user_id' => $actor->id, 'expires_at' => now()->addDays((int) config('event-assistant.retention_days', 30))]);
    }

    public function answer(Event $event, User $actor, array $data): array
    {
        $conversation = $this->conversation($event, $actor, $data['conversation_uuid'] ?? null);
        $context = $this->context($event, $actor);
        $previous = $this->previousOptions($conversation);
        $question = $data['message'];
        EventAssistantProposal::where('conversation_id', $conversation->id)->whereNull('applied_at')->update(['expires_at' => now()]);
        $conversation->messages()->create(['role' => 'user', 'content' => $question]);
        if ($reason = app(AssistantInterpreter::class)->unsupportedConstraint($question)) {
            $interpretation = ['action' => 'help', 'clarification' => $reason];
        } elseif (! empty($data['use_ai'])) {
            // Only event structure, never participant records or retained conversation transcripts, is sent.
            $interpretation = app(AssistantProvider::class)->interpret($question, array_intersect_key($context, array_flip(['event', 'dates', 'draws', 'venues', 'timezone'])), $previous);
        } else {
            $interpretation = app(AssistantInterpreter::class)->interpret($question, $context, $previous);
        }
        $options = $this->intentOptions(array_replace($previous, (array) ($interpretation['options'] ?? []), $data['options'] ?? []));
        if (! empty($interpretation['clarification'])) {
            return $this->reply($conversation, mb_substr((string) $interpretation['clarification'], 0, 600), ['options' => $options, 'outcome' => 'clarification']);
        }
        return match ($interpretation['action'] ?? 'help') {
            'schedule' => $this->scheduleProposal($event, $actor, $conversation, $options),
            'publication' => $this->publicationProposal($event, $actor, $conversation, $options),
            'draw_draft' => $this->drawProposal($event, $actor, $conversation, (string) ($interpretation['draw_name'] ?? '')),
            'draw_generate' => $this->generationProposal($event, $actor, $conversation, $options['draw_ids'] ?? []),
            default => $this->reply($conversation, 'I can preview schedules, review saved orders of play for publication, and create an empty individual draft draw. For seeding and bracket generation, continue in the draw guide.', ['options' => $options, 'outcome' => 'unsupported']),
        };
    }

    public function preview(Event $event, User $actor, array $data, bool $publication = false): array
    {
        Gate::forUser($actor)->authorize('event.manage', $event);
        $options = $this->intentOptions($data['options'] ?? $data);
        $conversation = $this->conversation($event, $actor, $data['conversation_uuid'] ?? null);
        return $publication ? $this->publicationProposal($event, $actor, $conversation, $options) : $this->scheduleProposal($event, $actor, $conversation, $options);
    }

    private function scheduleProposal(Event $event, User $actor, EventAssistantConversation $conversation, array $options): array
    {
        $context = $this->context($event, $actor);
        $this->checkSelectionScope($options, $context);
        if (! $context['draws']) {
            return $this->reply($conversation, 'This event has no draws yet. Create and prepare a draw before scheduling its matches.', ['options' => $options, 'outcome' => 'clarification']);
        }
        $options = array_replace($context['defaults'], $options);
        if (empty($options['date']) && count($context['dates']) === 1) $options['date'] = $context['dates'][0];
        if (empty($options['draw_ids']) && count($context['draws']) === 1) $options['draw_ids'] = [$context['draws'][0]['id']];
        if (empty($options['date']) || empty($options['draw_ids'])) {
            return $this->reply($conversation, 'Which event date and draws should I schedule? Choose them in the fields or include the exact date and draw names.', ['options' => $options, 'outcome' => 'clarification']);
        }
        $this->checkScope($options, $context);
        $fixtureCount = DB::table('fixtures')->whereIn('draw_id', $options['draw_ids'])->count()
            + DB::table('team_fixtures')->whereIn('draw_id', $options['draw_ids'])->count();
        abort_if($fixtureCount > 2000, 422, 'Select fewer draws; assistant previews are limited to 2,000 fixtures.');
        $draws = $event->draws()->whereIn('id', $options['draw_ids'])->with(['groups' => fn ($q) => $q->withCount('groupRegistrations'), 'drawFixtures', 'settings'])->get();
        if ($draws->contains('locked', true)) throw ValidationException::withMessages(['draw_ids' => 'Unlock selected draws through the existing draw workflow before scheduling.']);
        $rounds = [];
        $selectedKeys = [];
        foreach ($draws as $draw) {
            if (! $draw->isTeamDraw() && $draw->settings?->score_format === 'timed') {
                $timing = app(\App\Services\Scheduling\MatchTimingService::class)->forDraw($draw);
                if ($options['duration'] !== $timing['duration_minutes'] || $options['court_gap'] !== $timing['court_gap_minutes']) {
                    throw ValidationException::withMessages(['duration' => 'This timed draw uses '.$timing['duration_minutes'].' minutes of play and a '.$timing['court_gap_minutes'].' minute changeover. Use those saved settings, or select timed draws separately.']);
                }
            }
            $drawRoundNumbers = [];
            // Canonical day details carry graph-depth rounds for Flexible Monrad,
            // and skip automatic Bye nodes. Database fixture.round is not equivalent.
            foreach ($this->scheduler->dayRoundDetails($draw) as $roundNumber => $groups) {
                foreach ($groups as $group) foreach ($group['matches'] as $match) {
                    if ($match['played'] || ($match['scheduled_at'] && substr($match['scheduled_at'], 0, 10) !== $options['date'])) continue;
                    $selectedKeys[] = $match['key'];
                    $drawRoundNumbers[] = (int) $roundNumber;
                }
            }
            if ($drawRoundNumbers) $rounds[] = ['draw_id' => (int) $draw->id, 'rounds' => array_values(array_unique($drawRoundNumbers))];
        }
        if (count($rounds) !== $draws->count()) {
            return $this->reply($conversation, 'At least one selected draw has no matches available for this date. Prepare its draw first, or choose a date with unscheduled or saved matches. Played matches and other playing days stay unchanged.', ['options' => $options, 'outcome' => 'clarification']);
        }
        $venueIds = $options['venue_ids'] ?? [];
        if (! $venueIds) {
            $venueIds = $draws->flatMap(fn ($draw) => $draw->venues->pluck('id'))->unique()->map(fn ($id) => (int) $id)->values()->all();
        }
        if (! $venueIds) return $this->reply($conversation, 'Assign event venues and courts in the scheduling workspace first.', ['options' => $options, 'outcome' => 'clarification']);
        $labels = $context['court_labels'];
        $courts = [];
        foreach ($venueIds as $id) {
            $available = array_map('strval', $labels[$id] ?? []);
            $chosen = $options['courts'] ?? $available;
            if (! $chosen || array_diff($chosen, $available)) throw ValidationException::withMessages(['courts' => 'Choose existing courts at each selected event venue.']);
            $courts[$id] = $chosen;
        }
        $schedulerOptions = [
            'start' => $options['date'].' '.$options['start_time'].':00', 'end' => $options['date'].' '.$options['end_time'].':00',
            'full_day_scope' => $options['date'], 'draw_ids' => $options['draw_ids'], 'draw_rounds' => $rounds,
            'day_batch' => true, 'draw_order_mode' => 'mixed', 'draw_order' => $options['draw_ids'],
            'selected_fixture_keys' => array_values(array_unique($selectedKeys)),
            'venue_ids' => $venueIds, 'day_court_labels' => $courts,
            'duration' => $options['duration'], 'wave_minutes' => $options['duration'] + $options['court_gap'],
            'court_gap' => $options['court_gap'], 'player_rest' => $options['player_rest'],
            'allow_partial' => false, 'reschedule_existing' => false,
        ];
        // Deliberately bypass controller preview: it can prepare playoff fixtures.
        $preview = $this->scheduler->preview($event->fresh(), $schedulerOptions);
        $payload = ['options' => $schedulerOptions, 'revision' => $preview['revision'], 'intent' => $options];
        $proposal = $this->storeProposal($event, $actor, $conversation, 'schedule', $payload, $preview);
        return $this->reply($conversation, 'Review this private schedule preview. Existing saved bookings stay fixed. Saving does not publish the order of play.', ['options' => $options, 'outcome' => 'preview'], $proposal);
    }

    private function publicationProposal(Event $event, User $actor, EventAssistantConversation $conversation, array $options): array
    {
        $context = $this->context($event, $actor);
        $this->checkSelectionScope($options, $context);
        if (empty($options['date']) && count($context['dates']) === 1) $options['date'] = $context['dates'][0];
        if (empty($options['draw_ids']) && count($context['draws']) === 1) $options['draw_ids'] = [$context['draws'][0]['id']];
        if (empty($options['date']) || empty($options['draw_ids'])) return $this->reply($conversation, 'Choose an exact date and draws to review for publication.', ['options' => $options, 'outcome' => 'clarification']);
        $this->checkScope($options, $context);
        $this->boundPublicationWork($event);
        $rows = $this->publication->workingRows($event)->filter(fn ($row) => in_array((int) $row['draw_id'], $options['draw_ids'], true)
            && substr($row['scheduled_at'], 0, 10) === $options['date'])->values();
        if ($rows->isEmpty()) return $this->reply($conversation, 'There are no saved match times for that date and draw selection. Save a schedule first.', ['options' => $options, 'outcome' => 'clarification']);
        if ($event->draws()->whereIn('id', $options['draw_ids'])->where(fn ($q) => $q->where('published', false)->orWhere('locked', true))->exists()) {
            return $this->reply($conversation, 'The selected draws must be published and unlocked before assistant publication. Review their lifecycle in the draw workflow first.', ['options' => $options, 'outcome' => 'clarification']);
        }
        $scope = ['date' => $options['date'], 'fixture_keys' => $rows->map(fn ($row) => $row['fixture_kind'].':'.$row['fixture_id'])->all(), 'revision' => $this->publication->revision($event)];
        $proposal = $this->storeProposal($event, $actor, $conversation, 'publication', ['scope' => $scope, 'draw_ids' => $options['draw_ids'], 'intent' => $options], ['matches' => $rows->all(), 'count' => $rows->count(), 'date' => $options['date']]);
        return $this->reply($conversation, 'Review the exact saved matches below. Confirm publication to make these match times public. No announcement or email will be sent.', ['options' => $options, 'outcome' => 'preview'], $proposal);
    }

    private function drawProposal(Event $event, User $actor, EventAssistantConversation $conversation, string $name): array
    {
        Gate::forUser($actor)->authorize('individual-draw.create', $event);
        $data = Validator::make(['name' => $name], ['name' => ['required', 'string', 'max:120', 'regex:/\S/']])->validate();
        $types = DrawType::where('type', 'individual')->orderBy('id')->get();
        $type = $types->firstWhere('drawTypeName', 'Singles') ?? ($types->count() === 1 ? $types->first() : null);
        if (! $type) return $this->reply($conversation, 'Configure an individual Singles draw type first, or use the existing draw creation workflow to choose a discipline.', ['outcome' => 'clarification']);
        $payload = ['name' => $data['name'], 'draw_type_id' => (int) $type->id, 'revision' => $this->drawRevision($event)];
        $proposal = $this->storeProposal($event, $actor, $conversation, 'draw_draft', $payload, ['name' => $name, 'discipline' => $type->drawTypeName, 'scope' => 'Event-wide empty individual draft; no players, seeds or fixtures are generated. Continue in the draw guide.']);
        return $this->reply($conversation, 'Review this new empty individual draft draw. Confirming creates its setup record only; choose participants, format and seeds in the existing draw guide.', ['outcome' => 'preview'], $proposal);
    }

    public function generationPreview(Event $event, User $actor, array $data): array
    {
        $conversation = $this->conversation($event, $actor, $data['conversation_uuid'] ?? null);
        return $this->generationProposal($event, $actor, $conversation, [(int) $data['draw_id']]);
    }

    private function generationProposal(Event $event, User $actor, EventAssistantConversation $conversation, array $drawIds): array
    {
        if (count($drawIds) !== 1) return $this->reply($conversation, 'Choose exactly one prepared Round Robin draw for initial generation.', ['outcome' => 'clarification']);
        $review = app(AssistantRoundRobinGeneration::class)->review($event, $actor, $drawIds[0]);
        $proposal = $this->storeProposal($event, $actor, $conversation, 'draw_generate', ['draw_id' => $drawIds[0], 'revision' => $review['fingerprint']], $review['preview']);
        return $this->reply($conversation, 'Review the prepared groups and fixture counts. Confirmation generates initial Round Robin fixtures only; no schedule or publication changes.', ['options' => ['draw_ids' => $drawIds], 'outcome' => 'preview'], $proposal);
    }

    public function confirm(Event $event, User $actor, array $data): array
    {
        Gate::forUser($actor)->authorize('event.manage', $event);
        return DB::transaction(function () use ($event, $actor, $data) {
            $proposal = EventAssistantProposal::where('uuid', $data['proposal_uuid'])->where('event_id', $event->id)->where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($proposal->token_hash, hash('sha256', $data['confirmation_token'])), 422, 'Review the proposal before confirming.');
            abort_unless(hash_equals($proposal->fingerprint, ProposalFingerprint::make(['event_id' => $event->id, 'user_id' => $actor->id, 'type' => $proposal->type, 'payload' => $proposal->payload])), 409, 'The proposal changed. Generate a new preview.');
            if ($proposal->applied_at) return ['status' => 'already_applied', 'result' => $proposal->result];
            abort_if($proposal->expires_at->lte(now()), 409, 'This preview expired or was replaced. Generate a new preview.');
            $conversation = EventAssistantConversation::whereKey($proposal->conversation_id)->where('expires_at', '>', now())->firstOrFail();
            $payload = $proposal->payload;
            if ($proposal->type === 'schedule') {
                $current = $this->scheduler->preview($event->fresh(), $payload['options']);
                abort_if(empty($current['matches']) || ! empty($current['unscheduled']), 422, 'Resolve unscheduled matches before saving a nonempty schedule.');
                $result = $this->scheduler->apply($event->fresh(), $payload['options'], $payload['revision']);
            } elseif ($proposal->type === 'publication') {
                $this->boundPublicationWork($event);
                Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
                DB::table('events')->where('id', $event->id)->lockForUpdate()->first();
                $draws = $event->draws()->whereIn('id', $payload['draw_ids'])->orderBy('id')->lockForUpdate()->get();
                abort_if($draws->count() !== count($payload['draw_ids']) || $draws->contains(fn ($draw) => ! $draw->published || $draw->locked), 409, 'A selected draw was hidden, locked or removed. Review a new publication proposal.');
                $result = ['count' => $this->publication->publish($event->fresh(), $payload['scope']), 'published' => true];
            } elseif ($proposal->type === 'draw_generate') {
                $result = app(AssistantRoundRobinGeneration::class)->apply($event, $actor, $payload, $proposal->uuid);
            } elseif ($proposal->type === 'draw_draft') {
                Gate::forUser($actor)->authorize('individual-draw.create', $event);
                DB::table('events')->where('id', $event->id)->lockForUpdate()->first();
                abort_unless(hash_equals($payload['revision'], $this->drawRevision($event)), 409, 'The draw setup changed. Review a new draft proposal.');
                abort_if($event->draws()->whereRaw('LOWER(drawName) = ?', [mb_strtolower($payload['name'])])->exists(), 422, 'A draw with that name already exists in this event.');
                $type = DrawType::whereKey($payload['draw_type_id'])->where('type', 'individual')->firstOrFail();
                $draw = Draw::create(['event_id' => $event->id, 'drawName' => $payload['name'], 'drawType_id' => $type->id, 'category_event_id' => null, 'published' => false, 'oop_published' => false, 'locked' => false]);
                DrawAuditLog::record($draw->id, 'assistant_draft_created', null, ['event_id' => $event->id, 'proposal_uuid' => $proposal->uuid]);
                $result = ['draw_id' => $draw->id, 'setup_url' => route('draw.guide.show', [$event, $draw]), 'generated' => false];
            } else {
                abort(422, 'Unsupported assistant action.');
            }
            $proposal->update(['applied_at' => now(), 'result' => $result]);
            $conversation->messages()->create(['role' => 'assistant', 'content' => match ($proposal->type) {
                'schedule' => 'Schedule saved privately. Publication remains a separate action.',
                'publication' => 'The reviewed match times were published. No announcement was sent.',
                'draw_generate' => 'Initial Round Robin fixtures generated. Scheduling and publication remain separate actions.',
                default => 'Empty draft draw created. Continue in the draw guide to select players and generate fixtures.',
            }, 'metadata' => ['outcome' => 'applied', 'proposal_uuid' => $proposal->uuid, 'type' => $proposal->type, 'options' => $payload['intent'] ?? $this->previousOptions($conversation)]]);
            return ['status' => 'applied', 'type' => $proposal->type, 'result' => $result];
        }, 3);
    }

    public function feedback(Event $event, User $actor, array $data): void
    {
        $message = EventAssistantMessage::whereKey($data['message_id'])->where('role', 'assistant')->whereIn('conversation_id', EventAssistantConversation::where('event_id', $event->id)->where('user_id', $actor->id)->where('expires_at', '>', now())->select('id'))->firstOrFail();
        EventAssistantFeedback::updateOrCreate(['message_id' => $message->id, 'user_id' => $actor->id], ['rating' => $data['rating'], 'reason' => $data['reason'] ?? null, 'category' => FeedbackClassifier::classify($data['reason'] ?? ''), 'comment' => $data['comment'] ?? null]);
    }

    public function improvements(Event $event, User $actor): array
    {
        $conversationIds = EventAssistantConversation::where('event_id', $event->id)->where('user_id', $actor->id)->where('expires_at', '>', now())->select('id');
        $messageIds = EventAssistantMessage::whereIn('conversation_id', $conversationIds)->where('role', 'assistant')->select('id');
        $counts = EventAssistantFeedback::whereIn('message_id', $messageIds)->selectRaw('rating, category, COUNT(*) as count')->groupBy('rating', 'category')->get()->toArray();
        return ['feedback' => $counts, 'learning_mode' => 'reviewed_feedback', 'model_training' => false, 'scope' => 'Your feedback for this event only. No questions, comments, names or recordings are included.'];
    }

    public function savePreferences(Event $event, User $actor, array $preferences): array
    {
        $values = Validator::make($preferences, [
            'start_time' => ['sometimes', 'date_format:H:i'], 'end_time' => ['sometimes', 'date_format:H:i'],
            'duration' => ['sometimes', 'integer', 'between:15,240'], 'court_gap' => ['sometimes', 'integer', 'between:0,60'], 'player_rest' => ['sometimes', 'integer', 'between:0,480'],
        ])->validate();
        EventAssistantPreference::updateOrCreate(['event_id' => $event->id, 'user_id' => $actor->id], ['preferences' => $values]);
        return $values;
    }

    private function reply(EventAssistantConversation $conversation, string $content, array $metadata = [], ?array $proposal = null): array
    {
        $message = $conversation->messages()->create(['role' => 'assistant', 'content' => $content, 'metadata' => $metadata]);
        return ['conversation_uuid' => $conversation->uuid, 'message' => ['id' => $message->id, 'content' => $content], 'options' => $metadata['options'] ?? [], 'clarification' => ($metadata['outcome'] ?? '') === 'clarification' ? $content : null, 'proposal' => $proposal, 'suggestions' => ['Preview a schedule', 'Review publication', 'Create draw named New Singles']];
    }

    private function storeProposal(Event $event, User $actor, EventAssistantConversation $conversation, string $type, array $payload, array $preview): array
    {
        $token = Str::random(64);
        return DB::transaction(function () use ($event, $actor, $conversation, $type, $payload, $preview, $token) {
            EventAssistantProposal::where('conversation_id', $conversation->id)->whereNull('applied_at')->update(['expires_at' => now()]);
            $proposal = EventAssistantProposal::create(['uuid' => (string) Str::uuid(), 'conversation_id' => $conversation->id, 'event_id' => $event->id, 'user_id' => $actor->id, 'type' => $type, 'payload' => $payload, 'fingerprint' => ProposalFingerprint::make(['event_id' => $event->id, 'user_id' => $actor->id, 'type' => $type, 'payload' => $payload]), 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes((int) config('event-assistant.proposal_minutes', 15))]);
            return ['uuid' => $proposal->uuid, 'type' => $type, 'confirmation_token' => $token, 'fingerprint' => $proposal->fingerprint, 'expires_at' => $proposal->expires_at->toIso8601String(), 'preview' => $preview, 'can_confirm' => $type !== 'schedule' || (empty($preview['unscheduled']) && ! empty($preview['matches']))];
        });
    }

    private function intentOptions(array $input): array
    {
        // Accept the existing scheduling form's date/time names, but never its execution flags.
        foreach (['start' => 'start_time', 'end' => 'end_time'] as $key => $target) {
            if (isset($input[$key])) {
                Validator::make([$key => $input[$key]], [$key => ['required', 'date']], [
                    $key.'.required' => 'Choose a scheduling '.($key === 'start' ? 'start' : 'finish').' date and time.',
                    $key.'.date' => 'Choose a valid scheduling '.($key === 'start' ? 'start' : 'finish').' date and time.',
                ])->validate();
                $parsed = Carbon::parse($input[$key]);
                if ($key === 'end' && isset($input['date']) && $parsed->toDateString() !== $input['date']) {
                    throw ValidationException::withMessages(['end' => 'Choose one event date per assistant preview.']);
                }
                $input['date'] ??= $parsed->toDateString();
                $input[$target] = $parsed->format('H:i');
            }
        }
        $values = Validator::make($input, [
            'date' => ['sometimes', 'date_format:Y-m-d'], 'start_time' => ['sometimes', 'date_format:H:i'], 'end_time' => ['sometimes', 'date_format:H:i'],
            'draw_ids' => ['sometimes', 'array', 'min:1', 'max:200'], 'draw_ids.*' => ['integer', 'distinct', 'min:1'],
            'venue_ids' => ['sometimes', 'array', 'min:1', 'max:200'], 'venue_ids.*' => ['integer', 'distinct', 'min:1'],
            'courts' => ['sometimes', 'array', 'min:1', 'max:100'], 'courts.*' => ['string', 'distinct', 'max:50'],
            'duration' => ['sometimes', 'integer', 'between:15,240'], 'court_gap' => ['sometimes', 'integer', 'between:0,60'], 'player_rest' => ['sometimes', 'integer', 'between:0,480'],
        ], [
            'date.date_format' => 'Choose a valid event date.',
            'start_time.date_format' => 'Choose a valid start time.',
            'end_time.date_format' => 'Choose a valid finish time.',
            'draw_ids.array' => 'Choose draws from this event.',
            'draw_ids.min' => 'Choose at least one draw before requesting a preview.',
            'draw_ids.max' => 'Choose no more than 200 draws for one preview.',
            'draw_ids.*.integer' => 'Choose a draw from this event.',
            'draw_ids.*.distinct' => 'Choose each draw only once.',
            'venue_ids.array' => 'Choose venues from this event.',
            'venue_ids.min' => 'Choose at least one venue before requesting a preview.',
            'venue_ids.max' => 'Choose no more than 200 venues for one preview.',
            'venue_ids.*.integer' => 'Choose a venue from this event.',
            'venue_ids.*.distinct' => 'Choose each venue only once.',
            'courts.array' => 'Choose existing courts at the selected venues.',
            'courts.min' => 'Choose at least one court before requesting a preview.',
            'duration.between' => 'Match duration must be between 15 and 240 minutes.',
            'court_gap.between' => 'Changeover must be between 0 and 60 minutes.',
            'player_rest.between' => 'Player rest must be between 0 and 480 minutes.',
        ])->validate();
        foreach (['draw_ids', 'venue_ids'] as $key) if (isset($values[$key])) $values[$key] = array_map('intval', $values[$key]);
        foreach (['duration', 'court_gap', 'player_rest'] as $key) if (isset($values[$key])) $values[$key] = (int) $values[$key];
        return $values;
    }

    private function checkScope(array $options, array $context): void
    {
        if (! in_array($options['date'], $context['dates'], true)) throw ValidationException::withMessages(['date' => 'Choose a date within this event (up to 31 days).']);
        $this->checkSelectionScope($options, $context);
        if (isset($options['start_time'], $options['end_time']) && $options['start_time'] >= $options['end_time']) throw ValidationException::withMessages(['end_time' => 'Finish time must be after start time on the same date.']);
    }

    private function checkSelectionScope(array $options, array $context): void
    {
        if (isset($options['draw_ids']) && array_diff($options['draw_ids'], array_column($context['draws'], 'id'))) throw ValidationException::withMessages(['draw_ids' => 'Choose draws in this event.']);
        if (isset($options['venue_ids']) && array_diff($options['venue_ids'], array_column($context['venues'], 'id'))) throw ValidationException::withMessages(['venue_ids' => 'Choose venues in this event.']);
    }

    private function drawRevision(Event $event): string
    {
        return ProposalFingerprint::make(['draws' => $event->draws()->orderBy('id')->get(['id', 'drawName', 'updated_at'])->toArray(), 'types' => DrawType::where('type', 'individual')->orderBy('id')->get(['id', 'drawTypeName'])->toArray()]);
    }

    private function previousOptions(EventAssistantConversation $conversation): array
    {
        foreach ($conversation->messages()->where('role', 'assistant')->latest('id')->limit(12)->get(['metadata']) as $message) {
            if (! empty($message->metadata['options']) && is_array($message->metadata['options'])) return $message->metadata['options'];
        }
        return [];
    }

    private function boundPublicationWork(Event $event): void
    {
        $drawIds = $event->draws()->select('id');
        $count = DB::table('fixtures')->whereIn('draw_id', clone $drawIds)->count()
            + DB::table('team_fixtures')->whereIn('draw_id', clone $drawIds)->count();
        abort_if($count > 2000, 422, 'This event exceeds the 2,000-fixture assistant publication limit. Use the existing calendar publication workflow.');
    }
}
