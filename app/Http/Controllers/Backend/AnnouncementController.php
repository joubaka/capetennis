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
    $sendMail = (int) $request->send_email;

    // 🧾 Save announcement
    $announcement = new Announcement();
    $announcement->message = $request->data;
    $announcement->event_id = $request->event_id;
    $announcement->save();

    $event = Event::with(['eventType', 'region_in_events.teams.players', 'registrations.players'])->findOrFail($request->event_id);
    $emails = collect();

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

    // 💌 Prepare announcement data
    $data = [
      'message' => $request->data,
      'event' => $event->name,
    ];

    // 🚀 Send emails (via event)
    if ($sendMail === 1) {
      foreach ($emails as $email) {
        $data['email'] = $email;
        event(new AnnouncementPost($data));
      }

      return response()->json([
        'success' => true,
        'message' => "Announcement created and emails queued.",
        'emails_count' => $emails->count() // 👈 Add this line
      ]);
    }


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
