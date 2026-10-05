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
      return redirect()->route($communicationEvent->isTeam() ? 'backend.event-communications.index' : 'backend.interprovincial-trials.communications.index', $communicationEvent)
        ->withInput(['scope' => 'nominations', 'audience' => 'nominations', 'filter' => 'all', 'recipients' => 'both', 'subject' => $communicationEvent->name.' — Announcement', 'body' => trim(strip_tags((string) $request->data))])
        ->with('success', 'Choose all nominees or another audience, then review and approve the announcement email. No email has been sent.');
    }
    $request->validate(['data' => 'required|string|max:30000']);
    $announcement = Announcement::create(['event_id' => $communicationEvent->id, 'message' => $request->data]);
    if (! $request->boolean('send_email')) {
      return response()->json(['success' => true, 'message' => 'Announcement created successfully (no emails sent).']);
    }
    $service = app(\App\Services\EventAnnouncementService::class);
    $snapshot = $service->audienceSnapshot($communicationEvent);
    $stats = $service->dispatch($announcement, $snapshot['recipients'], $request->user(), $snapshot['excluded']);
    // Administrative copies are tracked separately from recipient results.
    $adminEmail = \App\Models\SiteSetting::get('admin_notification_email', 'support@capetennis.co.za');
    if ($adminEmail) app(\App\Services\BulkMailDispatcher::class)->dispatch('event_announcement', $announcement, [$adminEmail], [
      'event_id' => $communicationEvent->id, 'created_by' => $request->user()->id, 'recipient_kind' => 'admin_copy',
      'event_name' => $communicationEvent->name, 'title' => $announcement->title, 'message' => $announcement->message,
    ]);
    $severity = $stats['queued'] === 0 ? 'error' : (($stats['skipped'] || $stats['failed']) ? 'warning' : 'success');
    return response()->json([
      'success' => true, 'mail' => $stats, 'mail_level' => $severity,
      'emails_count' => $stats['queued'],
      'report_url' => route('backend.event-mail-log.index', $communicationEvent),
      'message' => "Announcement created; {$stats['queued']} recipient emails queued; {$stats['skipped']} skipped; {$stats['failed']} could not be queued. Check the event Email Log.",
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
