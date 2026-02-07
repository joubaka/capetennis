<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Announcement;
use App\Mail\AnnouncementMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;



class EventAnnouncementController extends Controller
{
  /* =========================
     LIST
  ========================= */


  public function index(Event $event)
  {
    $event->load([
      'announcements' => function ($q) {
        $q->withTrashed()
          ->latest();
      }
    ]);

    return view('backend.event.announcements', compact('event'));
  }


  /* =========================
     STORE
  ========================= */
  public function store(Request $request, Event $event)
  {
    $data = $request->validate([
      'title' => 'required|string|max:255',
      'message' => 'required|string',
      'sendMail' => 'nullable|boolean',
    ]);

    $announcement = $event->announcements()->create([
      'title' => $data['title'],
      'message' => $data['message'], // column is `message`
    ]);

    if (!empty($data['sendMail'])) {
      $this->emailAnnouncement($announcement);
    }

    return response()->json([
      'success' => true,
      'id' => $announcement->id,
    ]);
  }

  /* =========================
     SHOW (AJAX EDIT)
  ========================= */
  public function show(Announcement $announcement)
  {
    return response()->json([
      'id' => $announcement->id,
      'title' => $announcement->title,
      'message' => $announcement->message, // frontend expects `body`
    ]);
  }

  /* =========================
     UPDATE
  ========================= */
  public function update(Request $request, Announcement $announcement)
  {
    $data = $request->validate([
      'title' => 'required|string|max:255',
      'message' => 'required|string',
    ]);

    $announcement->update([
      'title' => $data['title'],
      'message' => $data['message'],
    ]);

    return response()->json(['success' => true]);
  }

  /* =========================
     EMAIL
  ========================= */
  protected function emailAnnouncement(Announcement $announcement)
  {
    if (!$announcement->event) {
      return;
    }

    $players = $announcement->event
      ->registrations()
      ->with('players')
      ->get()
      ->pluck('players')
      ->flatten()
      ->filter(fn($p) => !empty($p->email))
      ->unique('email');

    foreach ($players as $player) {
      Mail::to($player->email)->queue(
        new AnnouncementMail([
          'event' => $announcement->event->name,
          'title' => $announcement->title,
          'message' => $announcement->message,
        ])
      );
    }
  }



  public function destroy(Announcement $announcement)
  {
    $announcement->delete(); // ✅ SOFT DELETE

    return response()->json([
      'success' => true
    ]);
  }
  

  public function toggle(Request $request, $announcement)
  {
    $announcement = Announcement::withTrashed()->findOrFail($announcement);

    if ($announcement->trashed()) {
      $announcement->restore();
    } else {
      $announcement->delete();
    }

    return response()->json([
      'hidden' => $announcement->trashed(),
    ]);
  }


}
