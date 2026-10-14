<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Event, EventCommunicationBatch};
use App\Services\{CommunicationSender, EventCommunicationService, SelectionRegionMessage};
use Illuminate\Http\Request;

class SelectionRegionMailController extends Controller
{
    public function compose(Request $request, Event $event, SelectionRegionMessage $message)
    {
        $regions = $message->regions($event, $request->user());
        $draft = $message->draft($event);
        $selectedRegions = $regions->pluck('region_id')->all();
        if ($request->filled('edit_batch')) {
            $batch = EventCommunicationBatch::where('event_id', $event->id)->where('created_by', $request->user()->id)->findOrFail($request->query('edit_batch'));
            abort_unless(($batch->options['source'] ?? null) === 'selection_regions', 404);
            $draft['subject'] = $batch->subject;
            $draft['body'] = $batch->body;
            $draft['selection_fingerprint'] = $batch->options['selection_fingerprint'];
            $draft['from_name'] = $batch->options['from_name'];
            $draft['reply_to'] = $batch->options['reply_to'];
            $draft['bcc_emails'] = implode(', ', $batch->options['bcc_emails'] ?? []);
            $selectedRegions = $batch->options['selection_region_ids'];
        }
        return response()->view('backend.adminPage.selection-region-mail', compact('event', 'regions', 'draft', 'selectedRegions'))->header('Cache-Control', 'private, no-store');
    }

    public function preview(Request $request, Event $event, SelectionRegionMessage $message, EventCommunicationService $service)
    {
        $message->regions($event, $request->user());
        $data = $request->validate([
            'selection_region_ids' => 'required|array|min:1|max:500', 'selection_region_ids.*' => 'required|integer|distinct',
            'selection_fingerprint' => 'required|string|size:64', 'subject' => 'required|string|max:200', 'body' => 'required|string|max:30000',
            'bcc_emails' => 'nullable|string|max:13000',
        ]);
        $bcc = preg_split('/[,;\s]+/', trim($data['bcc_emails'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $bcc = collect($bcc)->map(fn ($email) => mb_strtolower($email))->unique()->values()->all();
        \Illuminate\Support\Facades\Validator::make(['bcc_emails' => $bcc], [
            'bcc_emails' => 'array|max:50', 'bcc_emails.*' => ['required', 'string', 'email:rfc', 'max:255'],
        ])->validate();
        $options = ['source' => 'selection_regions', 'scope' => 'all', 'recipients' => 'players', 'filter' => 'all',
            'bcc_emails' => $bcc,
            'selection_region_ids' => $data['selection_region_ids'], 'selection_fingerprint' => $data['selection_fingerprint']]
            + CommunicationSender::resolve($request->all(), $request->user());
        $batch = $service->preview($event, $request->user(), $options, $data['subject'], $data['body']);
        return response()->view('backend.event.communications.preview', compact('event', 'batch'))->header('Cache-Control', 'private, no-store');
    }
}
