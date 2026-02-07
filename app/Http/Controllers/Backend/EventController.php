<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Series;
use Barryvdh\DomPDF\Facade\Pdf as FacadePdf;
use Illuminate\Http\Request;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Draw;
use App\Models\DrawGroup;
use App\Models\DrawGroupRegistration;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\EventType;
use App\Models\User;


class EventController extends Controller
{
  public function saveCategories(Request $request)
  {
    $event = Event::find($request->event_id);
    $event->categories()->sync($request->selected);

    return $event->categories;
  }

  public function getEventCategories($eventId)
  {
    Log::info('[getEventCategories] Called', [
      'eventId' => $eventId,
      'user_id' => auth()->id(),
      'url' => request()->fullUrl(),
    ]);

    $rows = CategoryEvent::with('category')
      ->where('event_id', $eventId)
      ->orderBy('ordering')
      ->get();

    if ($rows->isEmpty()) {
      return response()->json([]);
    }

    return response()->json(
      $rows->map(fn($ce) => [
        'id' => $ce->id,
        'category_id' => $ce->category_id,
        'category_name' => $ce->category->name,
      ])->values()
    );
  }

  public function downloadTransactionsPDF($eventId)
  {
    $event = Event::with(['transactions.user', 'transactions.order.items'])
      ->findOrFail($eventId);

    $transactions = $event->transactions;

    $pdf = FacadePdf::loadView(
      'backend.adminPage.pdf.transactions',
      compact('event', 'transactions')
    );

    return $pdf->download("transactions_{$event->id}.pdf");
  }

  public function saveTeams(Request $request)
  {
    $eventId = $request->event_id;
    $teams = $request->teams;

    if (!$eventId || !$teams) {
      return response()->json(['error' => 'Invalid payload'], 422);
    }

    $categoryEvents = CategoryEvent::where('event_id', $eventId)
      ->with('category')
      ->get()
      ->keyBy(fn($ce) => Str::slug($ce->category->name));

    DB::transaction(function () use ($teams, $categoryEvents) {

      $drawIds = Draw::whereIn(
        'category_event_id',
        $categoryEvents->pluck('id')
      )->pluck('id');

      $oldGroupIds = DrawGroup::whereIn('draw_id', $drawIds)->pluck('id');

      DrawGroupRegistration::whereIn('draw_group_id', $oldGroupIds)->delete();
      DrawGroup::whereIn('draw_id', $drawIds)->delete();

      foreach ($teams as $index => $team) {

        $catSlug = $team['category'] ?? null;
        if (!$catSlug || !isset($categoryEvents[$catSlug])) {
          continue;
        }

        $categoryEvent = $categoryEvents[$catSlug];

        $draw = Draw::firstOrCreate(
          ['category_event_id' => $categoryEvent->id],
          ['drawName' => $categoryEvent->category->name . ' – Teams']
        );

        $group = DrawGroup::create([
          'draw_id' => $draw->id,
          'name' => $team['name'] ?? 'Team',
          'category_slug' => $catSlug,
          'color' => $team['color'] ?? 'primary',
          'sort_order' => $index + 1,
        ]);

        foreach ($team['players'] ?? [] as $rankIndex => $p) {
          $registration = CategoryEventRegistration::where('id', $p['id'])
            ->where('category_event_id', $categoryEvent->id)
            ->first();

          if ($registration) {
            DrawGroupRegistration::create([
              'draw_group_id' => $group->id,
              'registration_id' => $registration->id,
              'seed' => $rankIndex + 1,
            ]);
          }
        }
      }
    });

    return response()->json(['success' => true]);
  }

  public function edit(Event $event)
  {
    $eventTypes = EventType::orderBy('type')->get();
    $users = User::orderBy('name')->get();
    $adminIds = $event->admins()->pluck('users.id')->toArray();

    return view('backend.event.edit', compact(
      'event',
      'eventTypes',
      'users',
      'adminIds'
    ));
  }





  public function update(Request $request, Event $event)
  {
    Log::info('🛠 Event settings update START', [
      'event_id' => $event->id,
      'payload' => $request->all(),
      'user_id' => auth()->id(),
    ]);

    $data = $request->validate([
      'name' => 'required|string|max:255',
      'status' => 'nullable|string',
      'start_date' => 'nullable|date',
      'end_date' => 'nullable|date|after_or_equal:start_date',
      'information' => 'nullable|string',
      'venue_notes' => 'nullable|string',
      'entryFee' => 'nullable|integer',
      'deadline' => 'nullable|integer',
      'withdrawal_deadline' => 'nullable|date',
      'eventType' => 'required|integer',
      'email' => 'nullable|email',
      'published' => 'nullable|boolean',
      'signUp' => 'nullable|boolean',
      'organizer' => 'nullable|string|max:191',

      'logo_existing' => 'nullable|string',
      'logo_upload' => 'nullable|image|max:2048',

      'admins' => 'nullable|array',
      'admins.*' => 'integer|exists:users,id',
    ]);

    Log::debug('📥 Validated data', $data);

    // =========================
    // LOGO HANDLING
    // =========================
    if ($request->hasFile('logo_upload')) {
      $file = $request->file('logo_upload');

      $filename = Str::slug(
        pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
      ) . '.' . $file->getClientOriginalExtension();

      $file->move(public_path('assets/img/logos'), $filename);
      $event->logo = $filename;

      Log::info('🖼 Logo uploaded', ['logo' => $filename]);
    } elseif (!empty($data['logo_existing'])) {
      $event->logo = basename($data['logo_existing']);
    }

    // =========================
    // FIELD MAPPING (FIX)
    // =========================
    $updateData = [
      'name' => $data['name'],
      'status' => $data['status'] ?? $event->status,
      'start_date' => $data['start_date'] ?? null,
      'end_date' => $data['end_date'] ?? null,
      'information' => $data['information'] ?? null,
      'venue_notes' => $data['venue_notes'] ?? null,
      'entryFee' => $data['entryFee'] ?? null,
      'deadline' => $data['deadline'] ?? null,
      'withdrawal_deadline' => $data['withdrawal_deadline'] ?? null,
      'eventType' => $data['eventType'],
      'email' => $data['email'] ?? null,
      'published' => $request->boolean('published'),
      'signUp' => $request->boolean('signUp'),
      'organizer' => $data['organizer'] ?? null,
    ];

    Log::debug('🔁 Mapped data', $updateData);

    // =========================
    // UPDATE EVENT
    // =========================
    $event->update($updateData);

    // =========================
    // ADMINS
    // =========================
    if ($request->has('admins')) {
      Log::info('👥 Syncing admins', [
        'event_id' => $event->id,
        'admins' => $request->input('admins'),
      ]);

      $event->admins()->sync($request->input('admins', []));
    }

    Log::info('✅ Event settings update COMPLETE', [
      'event_id' => $event->id,
    ]);

    return response()->json(['success' => true]);
  }


}
