<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Announcement;
use App\Services\EventCommunicationService;
use App\Services\EventAnnouncementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EventAnnouncementController extends Controller
{
  /* =========================
     LIST
  ========================= */


  public function index(Event $event, EventAnnouncementService $announcements)
  {
    $this->authorize('event.manage', $event);

    $event->load([
      'announcements' => function ($q) {
        $q->withTrashed()
          ->latest();
      }
    ]);

    $snapshot = $announcements->audienceSnapshot($event);
    $announcementRecipients = $snapshot['recipients'];
    $announcementExcluded = $snapshot['excluded'];
    $announcementRecipientHash = $announcements->hashRecipients($announcementRecipients);

    return view('backend.event.announcements', compact(
      'event',
      'announcementRecipients',
      'announcementExcluded',
      'announcementRecipientHash',
    ));
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
      'title' => ['required', 'string', 'max:255'],
      'message' => ['required', 'string'],
      'sendMail' => 'nullable|boolean',
      'confirm_recipients' => ['nullable', 'accepted_if:sendMail,1'],
      'recipient_hash' => ['nullable', 'string', 'size:64'],
    ]);

    $this->ensureMessageHasContent($data['message']);

    if (!empty($data['sendMail'])) {
      $snapshot = $announcements->audienceSnapshot($event);
      $recipients = $snapshot['recipients'];
      $excluded = $snapshot['excluded'];
      if ($recipients->isEmpty()) {
        throw ValidationException::withMessages([
          'sendMail' => 'There are no valid nominated or registered player email addresses for this event.',
        ]);
      }

      $currentHash = $announcements->hashRecipients($recipients);
      if (! hash_equals($currentHash, (string) ($data['recipient_hash'] ?? ''))) {
        throw ValidationException::withMessages([
          'confirm_recipients' => 'The recipient list changed. Review the current recipients and confirm again.',
        ]);
      }
    }

    $announcement = $event->announcements()->create([
      'title' => $data['title'],
      'message' => $data['message'], // column is `message`
    ]);

    Log::debug('[EventAnnouncement] 💾 Announcement saved', [
      'id' => $announcement->id,
    ]);

    $batch = null;
    $mailRequested = !empty($data['sendMail']);
    if ($mailRequested) {
      try {
        $batch = $announcements->schedule($announcement, $recipients, $request->user(), $excluded);
      } catch (\Throwable) {
        Log::warning('Participant announcement emails could not be scheduled.', ['announcement_id' => $announcement->id]);
      }
    }

    $copyStats = $announcements->dispatchCopies($announcement, $request->user(), $batch ? $recipients : null);
    $mailStats = $mailRequested ? [
      'total' => $recipients->count() + $excluded->count(),
      'scheduled' => $batch ? $recipients->count() : 0,
      'skipped' => $excluded->count(),
      'invalid' => $excluded->count(),
      'failed' => $batch ? 0 : $recipients->count(),
    ] : null;
    $message = match (true) {
      $batch !== null => 'Announcement published. Participant emails are scheduled to begin in about '.EventAnnouncementService::EMAIL_DELAY_MINUTES.' minutes.',
      $mailRequested => 'Announcement published. Participant emails could not be scheduled. Review the event Email Log before retrying.',
      default => 'Announcement published on the event page. No participant emails requested.',
    };
    if ($batch && $excluded->isNotEmpty()) {
      $message .= ' '.$excluded->count().' player(s) have no valid contact address.';
    }
    $reportUrl = $batch && app(EventCommunicationService::class)->managesWholeEvent($event, $request->user())
      ? route('backend.event-communications.index', ['event' => $event->id, 'batch' => $batch->id, 'report_scope' => 'batch'])
      : route('backend.event-mail-log.index', $event);

    return response()->json([
      'success' => true,
      'id' => $announcement->id,
      'mail' => $mailStats,
      'copies' => $copyStats,
      'mail_level' => $mailRequested && ! $batch ? 'error' : (($mailStats['skipped'] ?? 0) || $copyStats['skipped'] || $copyStats['failed'] ? 'warning' : 'success'),
      'report_url' => $reportUrl,
      'message' => $message.$announcements->copyFeedback($copyStats),
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
      'message' => $announcement->message,
    ]);
  }

  /* =========================
     UPDATE
  ========================= */
  public function update(Request $request, Announcement $announcement)
  {
    $this->authorize('event.manage', $announcement->event);

    $data = $request->validate([
      'title' => ['required', 'string', 'max:255'],
      'message' => ['required', 'string'],
    ]);

    $this->ensureMessageHasContent($data['message']);

    $announcement->update([
      'title' => $data['title'],
      'message' => $data['message'],
    ]);

    return response()->json([
      'success' => true,
      'message' => 'Announcement updated. Previously queued emails were not resent.',
    ]);
  }

  public function destroy(Announcement $announcement)
  {
    $this->authorize('event.manage', $announcement->event);
    $announcement->delete(); // ✅ SOFT DELETE

    return response()->json([
      'success' => true,
      'message' => 'Announcement hidden from the public event page.',
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
      'message' => $announcement->trashed()
        ? 'Announcement hidden from the public event page.'
        : 'Announcement is visible on the public event page again.',
    ]);
  }

  private function ensureMessageHasContent(string $message): void
  {
    $plainText = trim(html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($plainText === '') {
      throw ValidationException::withMessages([
        'message' => 'Enter an announcement message before saving.',
      ]);
    }
  }

}
