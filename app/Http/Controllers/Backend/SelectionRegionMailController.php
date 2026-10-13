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
        ]);
        $options = ['source' => 'selection_regions', 'scope' => 'all', 'recipients' => 'players', 'filter' => 'all',
            'selection_region_ids' => $data['selection_region_ids'], 'selection_fingerprint' => $data['selection_fingerprint']]
            + CommunicationSender::resolve($request->all(), $request->user());
        $batch = $service->preview($event, $request->user(), $options, $data['subject'], $data['body']);
        return response()->view('backend.event.communications.preview', compact('event', 'batch'))->header('Cache-Control', 'private, no-store');
    }
}
