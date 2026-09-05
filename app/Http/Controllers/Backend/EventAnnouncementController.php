<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Announcement;
use App\Services\EventAnnouncementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EventAnnouncementController extends Controller
{
  /* =========================
     LIST
  ========================= */


  public function index(Event $event)
  {
    $this->authorize('event.manage', $event);

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
  public function store(Request $request, Event $event, EventAnnouncementService $announcements)
  {
    $this->authorize('event.manage', $event);

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

    $mailStats = null;
    if (!empty($data['sendMail'])) {
      Log::info('[EventAnnouncement] 📧 Sending emails...');
      $mailStats = $announcements->dispatch($announcement);
    } else {
      Log::info('[EventAnnouncement] ⏭️ sendMail not checked, skipping emails');
    }

    return response()->json([
      'success' => true,
      'id' => $announcement->id,
      'mail' => $mailStats,
    ]);
  }

  /* =========================
     SHOW (AJAX EDIT)
  ========================= */
  public function show(Announcement $announcement)
  {
    $this->authorize('event.manage', $announcement->event);

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
    $this->authorize('event.manage', $announcement->event);

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

  public function destroy(Announcement $announcement)
  {
    $this->authorize('event.manage', $announcement->event);
    $announcement->delete(); // ✅ SOFT DELETE

    return response()->json([
      'success' => true
    ]);
  }
  

  public function toggle(Request $request, $announcement)
  {
    $announcement = Announcement::withTrashed()->findOrFail($announcement);
    $this->authorize('event.manage', $announcement->event);

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
