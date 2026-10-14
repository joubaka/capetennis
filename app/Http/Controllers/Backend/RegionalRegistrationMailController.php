<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Event, EventCommunicationBatch};
use App\Services\{CommunicationSender, EventCommunicationService, RegionalRegistrationMessage};
use Illuminate\Http\Request;

final class RegionalRegistrationMailController extends Controller
{
    public function compose(Request $request, Event $event, RegionalRegistrationMessage $message)
    {
        $regions = $message->regions($event, $request->user());
        abort_if($regions->isEmpty(), 422, 'Add participating regions before preparing this email.');
        $selected = $regions->firstWhere('region_id', (int) $request->query('region_id')) ?? $regions->first();
        if ($request->filled('region_id')) abort_unless($regions->contains('region_id', (int) $request->query('region_id')), 403);
        $registrationClosesAt = $event->registrationClosesAt()?->timezone('Africa/Johannesburg');
        $options = ['event_region_id' => $selected->id, 'include_clothing' => false,
            'registration_deadline' => $registrationClosesAt?->format('Y-m-d'),
            'registration_deadline_time' => $registrationClosesAt?->format('H:i'),
            'clothing_deadline' => null, 'clothing_deadline_time' => null];
        $draft = $message->draft($event, $request->user(), $options);
        if ($request->filled('edit_batch')) {
            $batch = EventCommunicationBatch::where('event_id', $event->id)->where('created_by', $request->user()->id)->findOrFail($request->query('edit_batch'));
            abort_unless(($batch->options['source'] ?? '') === 'regional_registration_open', 404);
            $options = $batch->options;
            $draft = ['subject' => $batch->subject, 'body' => $batch->body,
                'registration_fingerprint' => $options['registration_fingerprint'],
                'from_name' => $options['from_name'], 'reply_to' => $options['reply_to']];
        }
        $context = $message->context($event, $request->user(), $options);
        return response()->view('backend.event.communications.regional-registration', compact('event', 'regions', 'options', 'draft', 'context'))->header('Cache-Control', 'private, no-store');
    }

    public function preview(Request $request, Event $event, RegionalRegistrationMessage $message, EventCommunicationService $communications)
    {
        $regions = $message->regions($event, $request->user());
        $data = $request->validate([
            'event_region_id' => 'required|integer', 'include_clothing' => 'nullable|boolean',
            'registration_deadline' => 'nullable|required_with:registration_deadline_time|date_format:Y-m-d',
            'clothing_deadline' => 'nullable|required_with:clothing_deadline_time|date_format:Y-m-d',
            'registration_deadline_time' => 'nullable|date_format:H:i',
            'clothing_deadline_time' => 'nullable|date_format:H:i',
            'intent' => 'required|in:generate,preview',
            'subject' => 'required_if:intent,preview|nullable|string|max:200',
            'body' => 'required_if:intent,preview|nullable|string|max:30000',
            'registration_fingerprint' => 'required_if:intent,preview|nullable|string|size:64',
        ]);
        $options = ['source' => 'regional_registration_open', 'scope' => 'all', 'recipients' => 'players', 'filter' => 'all',
            'event_region_id' => (int) $data['event_region_id'], 'include_clothing' => $request->boolean('include_clothing'),
            'registration_deadline' => $data['registration_deadline'] ?? null,
            'registration_deadline_time' => $data['registration_deadline_time'] ?? null,
            'clothing_deadline' => $data['clothing_deadline'] ?? null,
            'clothing_deadline_time' => $data['clothing_deadline_time'] ?? null]
            + CommunicationSender::resolve($request->all(), $request->user());
        if ($data['intent'] === 'generate') {
            $draft = $message->draft($event, $request->user(), $options)
                + ['from_name' => $options['from_name'], 'reply_to' => $options['reply_to']];
            $context = $message->context($event, $request->user(), $options);
            return response()->view('backend.event.communications.regional-registration', compact('event', 'regions', 'options', 'draft', 'context'))->header('Cache-Control', 'private, no-store');
        }
        $options['registration_fingerprint'] = $data['registration_fingerprint'];
        $batch = $communications->preview($event, $request->user(), $options, $data['subject'], $data['body']);
        return response()->view('backend.event.communications.preview', compact('event', 'batch'))->header('Cache-Control', 'private, no-store');
    }
}
