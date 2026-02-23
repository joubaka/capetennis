<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Announcement;
use App\Mail\AnnouncementMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

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
    Log::debug('[EventAnnouncement] 🚀 store() called', [
      'event_id' => $event->id,
      'sendMail' => $request->sendMail,
    ]);

    $data = $request->validate([
      'title' => 'required|string|max:255',
      'message' => 'required|string',
      'sendMail' => 'nullable|boolean',
    ]);

    $announcement = $event->announcements()->create([
      'title' => $data['title'],
      'message' => $data['message'], // column is `message`
    ]);

    Log::debug('[EventAnnouncement] 💾 Announcement saved', [
      'id' => $announcement->id,
    ]);

    if (!empty($data['sendMail'])) {
      Log::info('[EventAnnouncement] 📧 Sending emails...');
      $this->emailAnnouncement($announcement);
    } else {
      Log::info('[EventAnnouncement] ⏭️ sendMail not checked, skipping emails');
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
    Log::debug('[EventAnnouncement] 📬 emailAnnouncement() called', [
      'announcement_id' => $announcement->id,
    ]);

    if (!$announcement->event) {
      Log::warning('[EventAnnouncement] ❌ No event attached to announcement');
      return;
    }

    // Load required relationships
    $event = $announcement->event->load([
      'eventTypeModel',
      'regions.teams.players',
      'registrations.players',
    ]);

    $emails = collect();

    Log::debug('[EventAnnouncement] 📋 Event type', [
      'event_type' => $event->eventTypeModel?->type ?? 'null',
    ]);

    /*
    |--------------------------------------------------------------------------
    | TEAM EVENTS (type 3)
    |--------------------------------------------------------------------------
    */
    if ($event->isTeam()) {

      foreach ($event->regions as $region) {
        foreach ($region->teams as $team) {
          foreach ($team->players as $player) {
            if (!empty($player->email)) {
              $emails->push(strtolower(trim($player->email)));
            }
          }
        }
      }

    }
    /*
    |--------------------------------------------------------------------------
    | INDIVIDUAL EVENTS
    |--------------------------------------------------------------------------
    */ else {

      foreach ($event->registrations as $registration) {
        foreach ($registration->players as $player) {
          if (!empty($player->email)) {
            $emails->push(strtolower(trim($player->email)));
          }
        }
      }

    }

    // Remove duplicates + empty
    $emails = $emails->filter()->unique()->values();

    Log::info('[EventAnnouncement] 📋 Emails collected', [
      'count' => $emails->count(),
      'sample' => $emails->take(5)->toArray(),
    ]);

    if ($emails->isEmpty()) {
      Log::warning('[EventAnnouncement] ⚠️ No emails found for this event');
      return;
    }

    foreach ($emails as $email) {

      Log::debug('[EventAnnouncement] 📤 Queuing email', [
        'to' => $email,
      ]);

      Mail::to($email)->queue(
        new AnnouncementMail([
          'event' => $event->name,
          'title' => $announcement->title,
          'message' => $announcement->message,
        ])
      );
    }

    Log::info('[EventAnnouncement] ✅ All emails queued', [
      'count' => $emails->count(),
    ]);
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
