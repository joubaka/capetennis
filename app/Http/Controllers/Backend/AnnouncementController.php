<?php

namespace App\Http\Controllers\backend;

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
    Log::debug('[Announcement] 🚀 store() called', [
      'event_id' => $request->event_id,
      'send_email' => $request->send_email,
      'data_length' => strlen($request->data ?? ''),
    ]);

    $sendMail = (int) $request->send_email;

    // 🧾 Save announcement
    $announcement = new Announcement();
    $announcement->message = $request->data;
    $announcement->event_id = $request->event_id;
    $announcement->save();

    Log::debug('[Announcement] 💾 Announcement saved', [
      'announcement_id' => $announcement->id,
    ]);

    $event = Event::with(['eventType', 'region_in_events.teams.players', 'registrations.players'])->findOrFail($request->event_id);
    $emails = collect();

    Log::debug('[Announcement] 📋 Event loaded', [
      'event_name' => $event->name,
      'event_type' => $event->eventType?->type ?? 'null',
    ]);

    // 🎾 Team-based events (type 3)
    if ($event->eventType && $event->eventType->type == 3) {
      foreach ($event->region_in_events as $region) {
        foreach ($region->teams as $team) {
          foreach ($team->players as $player) {
            if (!empty($player->email)) {
              $emails->push(strtolower(trim($player->email)));
            }
          }
        }
      }
    }
    // 🧍‍♂️ Player-based events
    else {
      foreach ($event->registrations as $reg) {
        foreach ($reg->players as $player) {
          if (!empty($player->email)) {
            $emails->push(strtolower(trim($player->email)));
          }
        }
      }
    }

    // ✅ Always include admin
    $emails->push('hermanustennisacademy@gmail.com');

    // 🧹 Clean list (remove blanks + duplicates)
    $emails = $emails->filter()->unique()->values();

    Log::debug('[Announcement] 📧 Email list built', [
      'count' => $emails->count(),
      'emails' => $emails->take(5)->toArray(), // Log first 5 only
    ]);

    // 💌 Prepare announcement data
    $data = [
      'message' => $request->data,
      'event' => $event->name,
    ];

    // 🚀 Send emails (via event)
    if ($sendMail === 1) {
      Log::info('[Announcement] 📤 Dispatching events for emails', [
        'count' => $emails->count(),
      ]);

      foreach ($emails as $email) {
        $data['email'] = $email;
        
        Log::debug('[Announcement] 🎯 Firing AnnouncementPost event', [
          'email' => $email,
        ]);
        
        event(new AnnouncementPost($data));
      }

      Log::info('[Announcement] ✅ All events dispatched');

      return response()->json([
        'success' => true,
        'message' => "Announcement created and emails queued.",
        'emails_count' => $emails->count()
      ]);
    }

    Log::info('[Announcement] ⏭️ No emails sent (sendMail not checked)');

    return response()->json([
      'success' => true,
      'message' => "Announcement created successfully (no emails sent).",
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
