<?php

namespace App\Http\Controllers\Backend;

use App\Events\AnnouncementPost;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Player;
use App\Models\TeamRegion;
use finfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class AnnouncementController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
  public function store(Request $request)
  {
    $communicationEvent = Event::findOrFail($request->event_id);
    $this->authorize('event.manage', $communicationEvent);
    if ($request->boolean('send_email') && ($communicationEvent->isTeam() || $communicationEvent->isInterprovincialTrials())) {
      $request->validate(['data' => 'required|string|max:30000']);
      $announcement = new Announcement();
      $announcement->message = $request->data;
      $announcement->event_id = $communicationEvent->id;
      $announcement->save();
      $copyService = app(\App\Services\EventAnnouncementService::class);
      $copyStats = $copyService->dispatchCopies($announcement, $request->user());
      return redirect()->route($communicationEvent->isTeam() ? 'backend.event-communications.index' : 'backend.interprovincial-trials.communications.index', $communicationEvent)
        ->withInput(['scope' => 'nominations', 'audience' => 'nominations', 'filter' => 'all', 'recipients' => 'both', 'subject' => $communicationEvent->name.' — Announcement', 'body' => trim(strip_tags((string) $request->data))])
        ->with('success', 'Choose all nominees or another audience, then review and approve the participant email.'.$copyService->copyFeedback($copyStats));
    }
    $request->validate(['data' => 'required|string|max:30000']);
    $announcement = Announcement::create(['event_id' => $communicationEvent->id, 'message' => $request->data]);
    if (! $request->boolean('send_email')) {
      $service = app(\App\Services\EventAnnouncementService::class);
      $copies = $service->dispatchCopies($announcement, $request->user());
      return response()->json(['success' => true, 'copies' => $copies, 'mail_level' => $copies['failed'] || $copies['skipped'] ? 'warning' : 'success', 'message' => 'Announcement created. No participant emails requested.'.$service->copyFeedback($copies)]);
    }
    $service = app(\App\Services\EventAnnouncementService::class);
    $snapshot = $service->audienceSnapshot($communicationEvent);
    $stats = $service->dispatch($announcement, $snapshot['recipients'], $request->user(), $snapshot['excluded']);
    $copies = $service->dispatchCopies($announcement, $request->user());
    $severity = $stats['queued'] === 0 ? 'error' : (($stats['skipped'] || $stats['failed'] || $copies['skipped'] || $copies['failed']) ? 'warning' : 'success');
    return response()->json([
      'success' => true, 'mail' => $stats, 'copies' => $copies, 'mail_level' => $severity,
      'emails_count' => $stats['queued'],
      'report_url' => route('backend.event-mail-log.index', $communicationEvent),
      'message' => "Announcement created; {$stats['queued']} recipient emails queued; {$stats['skipped']} skipped; {$stats['failed']} could not be queued. Check the event Email Log.".$service->copyFeedback($copies),
    ]);
  }

  /**
   * Display the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
