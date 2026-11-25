<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Finance\Services\EventPayoutRequestService;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPayoutRequest;
use Illuminate\Http\Request;

class EventPayoutRequestController extends Controller
{
    public function show(Request $request, Event $event, EventPayoutRequestService $service)
    {
        $service->authorize($event, $request->user());
        $snapshot = $service->snapshot($event);
        $previewToken = $service->token($snapshot);
        $requests = EventPayoutRequest::where('event_id', $event->id)->latest('id')->paginate(15);
        $outstanding = EventPayoutRequest::where('event_id', $event->id)->whereIn('status', ['pending', 'reviewed'])->exists();

        return response()->view('backend.event.payout-requests', compact('event', 'snapshot', 'previewToken', 'requests', 'outstanding'))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, Event $event, EventPayoutRequestService $service)
    {
        $service->request($event, $request->user(), $request->all());

        return redirect()->route('admin.events.payout-requests', $event)->with('success', 'Request saved for Super User review. No payment has been made.');
    }

    public function complete(Request $request, Event $event, EventPayoutRequestService $service)
    {
        $service->complete($event, $request->user(), $request->all());

        return redirect()->route('admin.events.payout-requests', $event)->with('success', 'Event marked completed. Review the balance below to request the final payout.');
    }

    public function index(Request $request, EventPayoutRequestService $service)
    {
        abort_unless($request->user()->hasRole('super-user'), 403);
        $requests = EventPayoutRequest::with(['event', 'requester'])->whereIn('status', ['pending', 'reviewed'])->oldest('id')->paginate(15);
        $previews = [];
        foreach ($requests as $item) {
            if ($item->event) {
                $previews[$item->event_id] = $service->snapshot($item->event);
            }
        }

        return response()->view('backend.superadmin.payout-requests', compact('requests', 'previews', 'service'))->header('Cache-Control', 'private, no-store');
    }

    public function review(Request $request, Event $event, EventPayoutRequest $payoutRequest, EventPayoutRequestService $service)
    {
        $service->review($event, $payoutRequest, $request->user(), $request->all());

        return redirect()->route('superadmin.payout-requests')->with('success', 'Review saved. This does not approve or record a payment.');
    }
}
