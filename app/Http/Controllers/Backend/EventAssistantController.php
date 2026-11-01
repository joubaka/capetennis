<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Assistant\{AssistantTranscriptionProvider, AssistantProviderException, AssistantUsageLimits, EventAssistantService};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Schema;

class EventAssistantController extends Controller
{
    public function __construct(private readonly EventAssistantService $assistant) {}

    public function workspace(Event $event)
    {
        $this->guard($event, false);

        return view('backend.event.assistant-workspace', compact('event'));
    }

    public function context(Request $request, Event $event): JsonResponse
    {
        $this->guard($event, false);
        return response()->json($this->assistant->context($event, $request->user()));
    }

    public function message(Request $request, Event $event, AssistantUsageLimits $limits): JsonResponse
    {
        $this->guard($event);
        $data = $request->validate(['message' => ['required', 'string', 'max:2000'], 'conversation_uuid' => ['nullable', 'uuid'], 'use_ai' => ['sometimes', 'boolean'], 'options' => ['sometimes', 'array']]);
        $limits->consume($request->user()->id, 'message');
        return $this->respond(fn () => $this->assistant->answer($event, $request->user(), $data));
    }

    public function conversation(Request $request, Event $event, string $uuid): JsonResponse
    {
        $this->guard($event);
        validator(['uuid' => $uuid], ['uuid' => ['required', 'uuid']])->validate();
        $conversation = $this->assistant->conversation($event, $request->user(), $uuid);
        $messages = $conversation->messages()->latest('id')->limit(30)->get(['id', 'role', 'content', 'created_at'])->reverse()->values();
        return response()->json(['conversation_uuid' => $conversation->uuid, 'messages' => $messages]);
    }

    public function preview(Request $request, Event $event): JsonResponse
    {
        $this->guard($event);
        $data = $request->validate(['conversation_uuid' => ['nullable', 'uuid'], 'options' => ['required', 'array']]);
        return $this->respond(fn () => $this->assistant->preview($event, $request->user(), $data));
    }

    public function publicationPreview(Request $request, Event $event): JsonResponse
    {
        $this->guard($event);
        $data = $request->validate(['conversation_uuid' => ['nullable', 'uuid'], 'options' => ['required', 'array']]);
        return $this->respond(fn () => $this->assistant->preview($event, $request->user(), $data, true));
    }

    public function confirm(Request $request, Event $event): JsonResponse
    {
        $this->guard($event);
        $data = $request->validate(['proposal_uuid' => ['required', 'uuid'], 'confirmation_token' => ['required', 'string', 'size:64'], 'confirmed' => ['required', 'accepted']]);
        return $this->respond(fn () => $this->assistant->confirm($event, $request->user(), $data));
    }

    public function generationPreview(Request $request, Event $event): JsonResponse
    {
        $this->guard($event);
        $data = $request->validate(['conversation_uuid' => ['nullable', 'uuid'], 'draw_id' => ['required', 'integer', 'min:1']]);
        return $this->respond(fn () => $this->assistant->generationPreview($event, $request->user(), $data));
    }

    public function feedback(Request $request, Event $event): JsonResponse
    {
        $this->guard($event);
        $data = $request->validate(['message_id' => ['required', 'integer'], 'rating' => ['required', 'in:helpful,unhelpful'], 'reason' => ['nullable', 'string', 'max:40'], 'comment' => ['nullable', 'string', 'max:1000']]);
        $this->assistant->feedback($event, $request->user(), $data);
        return response()->json(['message' => 'Feedback saved for review. It does not automatically train a model.']);
    }

    public function preferences(Request $request, Event $event): JsonResponse
    {
        $this->guard($event);
        $data = $request->validate(['preferences' => ['required', 'array:start_time,end_time,duration,court_gap,player_rest'], 'confirmed' => ['required', 'accepted']]);
        return response()->json(['preferences' => $this->assistant->savePreferences($event, $request->user(), $data['preferences'])]);
    }

    public function improvements(Request $request, Event $event): JsonResponse
    {
        $this->guard($event);
        return response()->json($this->assistant->improvements($event, $request->user()));
    }

    public function transcribe(Request $request, Event $event, AssistantTranscriptionProvider $provider, AssistantUsageLimits $limits): JsonResponse
    {
        $this->guard($event);
        abort_unless($provider->isLocal() || config('event-assistant.mode') === 'local_first', 403, 'External voice is disabled in local-only mode.');
        $consent = $provider->isLocal() ? 'transcription_consent' : 'use_ai';
        $data = $request->validate([$consent => ['required', 'accepted'], 'audio' => ['required', 'file', 'max:5120', 'mimetypes:audio/wav,audio/x-wav,audio/webm,video/webm,audio/mp4,video/mp4,audio/ogg,application/ogg']]);
        $limits->consume($request->user()->id, 'voice');
        return $this->respond(fn () => ['transcript' => $provider->transcribe($data['audio'], $event)]);
    }

    private function guard(Event $event, bool $database = true): void
    {
        abort_unless(auth()->user()?->hasRole('super-user'), 403);
        $this->authorize('event.manage', $event);
        abort_unless(config('event-assistant.enabled'), 404);
        if ($database) abort_unless(Schema::hasTable('event_assistant_conversations'), 503, 'The assistant database setup is not available yet.');
    }

    private function respond(callable $operation): JsonResponse
    {
        try {
            return response()->json($operation());
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (AssistantProviderException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        } catch (\Illuminate\Database\QueryException $exception) {
            report($exception);

            return response()->json(['message' => 'The assistant database setup needs attention. Complete the required local updates and review the saved calendar before retrying a confirmation.'], 503);
        }
    }
}
