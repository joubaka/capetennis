<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\CategoryEvent;
use App\Models\Registration;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

use Maatwebsite\Excel\Facades\Excel;

use App\Mail\BulkEventMail;
use App\Exports\EventEntriesExport;
use App\Exports\CategoryEntriesExport;

class EventEntryController extends Controller
{
  /**
   * Show entries page (grouped per category).
   */
  public function index(Event $event)
  {
    $categoryEvents = $event->eventCategories()
      ->with([
        'category',
        'categoryEventRegistrations.registration.players',
      ])
      ->get();

    return view('backend.event.entries', compact('event', 'categoryEvents'));
  }

  /**
   * Lock a category.
   */
  public function lock(CategoryEvent $categoryEvent)
  {
    $categoryEvent->update([
      'locked_at' => now(),
    ]);

    return response()->json([
      'success' => true,
      'locked' => true,
    ]);
  }

  /**
   * Unlock a category.
   */
  public function unlock(CategoryEvent $categoryEvent)
  {
    $categoryEvent->update([
      'locked_at' => null,
    ]);

    return response()->json([
      'success' => true,
      'locked' => false,
    ]);
  }

  /**
   * Add a registration to a category.
   */
  public function addPlayer(Request $request, CategoryEvent $categoryEvent)
  {
    if ($categoryEvent->isLocked()) {
      return response()->json([
        'success' => false,
        'message' => 'Category is locked',
      ], 403);
    }

    $data = $request->validate([
      'registration_id' => ['required', 'exists:registrations,id'],
    ]);

    $alreadyExists = $categoryEvent->categoryEventRegistrations()
      ->where('registration_id', $data['registration_id'])
      ->exists();

    if ($alreadyExists) {
      return response()->json([
        'success' => false,
        'message' => 'Player already in category',
      ], 422);
    }

    $entry = $categoryEvent->categoryEventRegistrations()->create([
      'registration_id' => $data['registration_id'],
      'status' => 'active',
    ]);

    $entry->load('registration.players');

    return response()->json([
      'success' => true,
      'count' => $categoryEvent->categoryEventRegistrations()->count(),
      'row' => view('backend.event.partials.entry-row', [
        'reg' => $entry,
      ])->render(),
    ]);
  }

  /**
   * Remove a registration from a category.
   */
  public function removePlayer(CategoryEvent $categoryEvent, Registration $registration)
  {
    if ($categoryEvent->isLocked()) {
      return response()->json([
        'success' => false,
        'message' => 'Category is locked',
      ], 403);
    }

    $categoryEvent->categoryEventRegistrations()
      ->where('registration_id', $registration->id)
      ->delete();

    return response()->json([
      'success' => true,
      'count' => $categoryEvent->categoryEventRegistrations()->count(),
    ]);
  }

  /**
   * Export all event entries.
   */
  public function exportEvent(Event $event)
  {
    return Excel::download(
      new EventEntriesExport($event),
      "event_{$event->id}_entries.xlsx"
    );
  }

  /**
   * Export entries for a single category.
   */
  public function exportCategory(CategoryEvent $categoryEvent)
  {
    return Excel::download(
      new CategoryEntriesExport($categoryEvent),
      "category_{$categoryEvent->id}_entries.xlsx"
    );
  }

  /**
   * Send bulk email (player / category / event).
   */
  public function sendEmail(Request $request)
  {
    /* =========================
       VALIDATE INPUT
    ========================= */
    $data = $request->validate([
      'scope' => 'required|in:player,category,event',
      'event_id' => 'required|exists:events,id',
      'category_event_id' => 'nullable|exists:category_events,id',
      'registration_id' => 'nullable|exists:registrations,id',

      'subject' => 'required|string|max:255',
      'message' => 'required|string',

      'from_name' => 'required|string|max:100',
      'reply_to' => 'required|email|max:255',
    ]);

    Log::info('📨 Bulk email request validated', [
      'payload' => collect($data)->except('message'),
      'preview' => str($data['message'])->limit(120),
    ]);

    $emails = collect();

    /* =========================
       RESOLVE RECIPIENTS
    ========================= */
    if ($data['scope'] === 'player') {

      $reg = Registration::with('players')->findOrFail($data['registration_id']);
      $emails = $reg->players->pluck('email');

      Log::info('📍 Scope: player', [
        'registration_id' => $reg->id,
        'players' => $reg->players->pluck('id'),
      ]);

    } elseif ($data['scope'] === 'category') {

      $categoryEvent = CategoryEvent::with(
        'categoryEventRegistrations.registration.players'
      )->findOrFail($data['category_event_id']);

      $emails = $categoryEvent->categoryEventRegistrations
        ->flatMap(fn($r) => $r->registration->players)
        ->pluck('email');

      Log::info('📍 Scope: category', [
        'category_event_id' => $categoryEvent->id,
        'registrations' => $categoryEvent->categoryEventRegistrations->pluck('registration_id'),
      ]);

    } else {

      $event = Event::with('registrations.players')->findOrFail($data['event_id']);

      $emails = $event->registrations
        ->flatMap(fn($r) => $r->players)
        ->pluck('email');

      Log::info('📍 Scope: event', [
        'event_id' => $event->id,
        'registration_count' => $event->registrations->count(),
      ]);
    }

    /* =========================
       CLEAN EMAIL LIST
    ========================= */
    $emails = $emails->filter()->unique()->values();

    Log::info('📧 Final email list prepared', [
      'total' => $emails->count(),
      'sample' => $emails->take(5),
    ]);

    if ($emails->isEmpty()) {
      return response()->json([
        'success' => false,
        'message' => 'No valid email recipients found.',
      ], 422);
    }

    /* =========================
       QUEUE EMAILS
    ========================= */
    foreach ($emails as $email) {

      Log::info('➡️ Queuing bulk email', [
        'to' => $email,
        'subject' => $data['subject'],
        'from' => $data['from_name'],
        'reply_to' => $data['reply_to'],
      ]);

      Mail::to($email)->queue(
        new BulkEventMail(
          $data['subject'],
          $data['message'],
          $data['from_name'],
          $data['reply_to']
        )
      );
    }

    Log::info('✅ Bulk email queue completed', [
      'sent_count' => $emails->count(),
      'scope' => $data['scope'],
      'event_id' => $data['event_id'],
    ]);

    return response()->json([
      'success' => true,
      'sent' => $emails->count(),
      'queued' => true,
    ]);
  }

  public function availableRegistrations(CategoryEvent $categoryEvent)
  {
    // Registrations already used in this category
    $used = $categoryEvent->categoryEventRegistrations()
      ->pluck('registration_id');

    return $categoryEvent->event
      ->registrations() // ← HasManyThrough → category_event_registrations
      ->with('players')
      ->whereNotIn(
        'category_event_registrations.registration_id',
        $used
      )
      ->get()
      ->map(fn($r) => [
        'id' => $r->registration_id, // IMPORTANT
        'name' =>
          optional($r->players->first())->name . ' ' .
          optional($r->players->first())->surname,
      ]);
  }



}
