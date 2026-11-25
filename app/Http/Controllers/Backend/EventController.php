<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Services\FinancialLedgerService;
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
use Illuminate\Support\Facades\File;
use App\Models\EventType;
use App\Models\User;
use App\Services\EventBriefAiService;
use Illuminate\Http\JsonResponse;


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
    $event = Event::findOrFail($eventId);
    $this->authorize('event-finance.view', $event);

    /** @var FinancialLedgerService $ledgerService */
    $ledgerService = app(FinancialLedgerService::class);

    $built       = $ledgerService->buildForEvent($event);
    $paymentRows = $built['paymentRows'];
    $refundRows  = $built['refundRows'];
    $payoutRows  = $built['payoutRows'];
    $totals      = $built['totals'];

    $receiptSections = app(\App\Domain\Finance\Services\EventFinanceReceiptReport::class)->build($event, $paymentRows->merge($refundRows));

    $entryRows = $paymentRows->whereIn('type', ['payment', 'admin_entry_fee']);
    $totalEntries = $entryRows->sum(fn($r) => $r->entryCount ?? 1);

    $ledger = collect()
      ->merge($paymentRows)
      ->merge($refundRows)
      ->merge($payoutRows)
      ->sortByDesc('created_at')
      ->values();

    $pdf = FacadePdf::loadView('backend.adminPage.pdf.transactions', [
      'event'              => $event,
      'ledger'             => $ledger,
      'receiptSections'    => $receiptSections,
      'totalEntries'       => $totalEntries,
      'totalGross'         => $totals['gross_payments'],
      'totalPayfastFees'   => $totals['pf_fees'],
      'totalCapeTennisFees'=> $totals['cape_fees'],
      'totalPayouts'       => $totals['total_paid_out'],
      'netTournamentIncome'=> $totals['net_revenue'],
      'totalWithdrawalFees'=> 0,
    ]);

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

  public function create(Request $request)
  {
    $hostApplication = null;
    $sourceEvent = null;
    if ($request->filled('host_application')) {
      abort_unless($request->user()->hasRole('super-user'), 403);
      $request->validate(['host_application' => 'required|integer']);
      $hostApplication = \App\Models\HostEventApplication::findOrFail($request->integer('host_application'));
      abort_unless($hostApplication->status === 'approved', 409, 'Approve the hosting proposal before preparing its event.');
      if ($hostApplication->event_id) return redirect()->route('backend.events.edit', $hostApplication->event_id);
      $sourceEvent = new Event([
        'name' => $hostApplication->event_name, 'start_date' => $hostApplication->start_date,
        'end_date' => $hostApplication->end_date, 'email' => $hostApplication->email,
        'organizer' => $hostApplication->academy, 'eventType' => EventType::INDIVIDUAL,
        'venue_notes' => $hostApplication->venue.', '.$hostApplication->town,
      ]);
    }
    $eventTypes = EventType::orderBy('name')->get();
    $users = User::orderBy('name')->get();

    return view('backend.event.create', [
      'eventTypes'  => $eventTypes,
      'users'       => $users,
      'adminIds'    => [],
      'sourceEvent' => $sourceEvent,
      'hostApplication' => $hostApplication,
      'isCopy'      => false,
      'logoFiles'   => $this->availableEventLogos(),
    ]);
  }

  public function previewBrief(Request $request, EventBriefAiService $assistant): JsonResponse
  {
    $data = $request->validate([
      'brief' => 'required|string|min:20|max:10000',
    ]);

    $eventTypes = EventType::query()
      ->orderBy('name')
      ->get(['id', 'name'])
      ->map(fn (EventType $type) => ['id' => $type->id, 'name' => $type->name])
      ->all();

    try {
      $draft = $assistant->extract($data['brief'], $eventTypes, (int) auth()->id());
    } catch (\RuntimeException $exception) {
      Log::warning('AI event brief extraction failed', [
        'user_id' => auth()->id(),
        'message' => $exception->getMessage(),
      ]);

      return response()->json(['message' => $exception->getMessage()], 503);
    }

    return response()->json(['draft' => $draft]);
  }

  public function store(Request $request)
  {
    if ($request->exists('pathway_level')) $this->authorize('event.create');
    if ($request->filled('source_event_id') && !auth()->user()->hasRole('super-user')) {
      abort(403, 'Only super users can duplicate events.');
    }

    $data = $request->validate([
      'name'                => 'required|string|max:255',
      'start_date'          => 'nullable|date',
      'end_date'            => 'nullable|date|after_or_equal:start_date',
      'information'         => 'nullable|string',
      'venue_notes'         => 'nullable|string',
      'entryFee'            => 'nullable|integer',
      'deadline'            => 'nullable|integer',
      'withdrawal_deadline' => 'nullable|date',
      'eventType'           => 'required|integer',
      'email'               => 'nullable|email',
      'organizer'           => 'nullable|string|max:191',
      'logo_upload'         => 'nullable|image|max:2048',
      'logo_existing'       => [
        'nullable',
        'string',
        'max:255',
        function (string $attribute, mixed $value, \Closure $fail): void {
          if ($value !== null && ! is_file(public_path('assets/img/logos/'.basename($value)))) {
            $fail('The selected event logo is no longer available.');
          }
        },
      ],
      'admins'              => 'nullable|array',
      'admins.*'            => 'integer|exists:users,id',
      'source_event_id'     => 'nullable|integer|exists:events,id',
      'host_application_id' => 'nullable|integer',
      'pathway_level' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Services\Performance\EventPathwayClassification::HOSTED_LEVELS))],
    ]);

    if (!empty($data['host_application_id'])) {
      abort_unless($request->user()->hasRole('super-user'), 403);
      abort_if(!empty($data['source_event_id']), 422, 'Create a hosting proposal event without copying another event.');
      abort_unless(EventType::whereKey($data['eventType'])->exists(), 422, 'Select a valid event type.');
    }

    $sourceEvent = null;
    if (!empty($data['source_event_id'])) {
      $sourceEvent = Event::with([
        'categoryEvents',
        'admins:id',
      ])->findOrFail($data['source_event_id']);
    }

    // Production's legacy schema requires a non-null logo. A copied event
    // keeps the source logo unless the user uploads a replacement.
    $logo = !empty($data['logo_existing'])
      ? basename($data['logo_existing'])
      : ($sourceEvent?->logo ?? '');
    if ($request->hasFile('logo_upload')) {
      $file     = $request->file('logo_upload');
      $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
        . '.' . $file->getClientOriginalExtension();
      $file->move(public_path('assets/img/logos'), $filename);
      $logo = $filename;
    }

    $newAdmins = [];
    if ($request->has('admins')) {
      $newAdmins = $data['admins'] ?? [];
    } elseif (!$sourceEvent) {
      $newAdmins = [];
    }

    if ($sourceEvent && empty($newAdmins)) {
      $newAdmins = $sourceEvent->admins->pluck('id')->toArray();
    }

    $event = DB::transaction(function () use ($data, $request, $logo, $sourceEvent, $newAdmins) {
      $hostApplication = null;
      if (!empty($data['host_application_id'])) {
        $hostApplication = \App\Models\HostEventApplication::lockForUpdate()->findOrFail($data['host_application_id']);
        abort_unless($hostApplication->status === 'approved', 409, 'Approve the hosting proposal before creating its event.');
        if ($hostApplication->event_id) return Event::lockForUpdate()->findOrFail($hostApplication->event_id);
      }
      $event = Event::create([
        'name'                => $data['name'],
        'start_date'          => $data['start_date'] ?? null,
        'end_date'            => $data['end_date'] ?? null,
        'information'         => $data['information'] ?? null,
        'venue_notes'         => $data['venue_notes'] ?? null,
        'pathway_level'       => $data['pathway_level'] ?? null,
        'entryFee'            => $data['entryFee'] ?? null,
        'deadline'            => $data['deadline'] ?? 7,
        'withdrawal_deadline' => $data['withdrawal_deadline'] ?? null,
        'eventType'           => $data['eventType'],
        'email'               => $data['email'] ?? null,
        'organizer'           => $data['organizer'] ?? null,
        'logo'                => $logo,
        'published'           => $hostApplication ? false : $request->boolean('published'),
        'signUp'              => $hostApplication ? false : $request->boolean('signUp'),
      ]);

      if ($sourceEvent) {
        $sourceEvent->categoryEvents->each(function ($categoryEvent) use ($event) {
          $newCat = $categoryEvent->replicate();
          $newCat->event_id = $event->id;
          $newCat->save();
        });

      }

      $event->admins()->sync($newAdmins);
      if ($hostApplication) $hostApplication->update(['event_id' => $event->id]);

      return $event;
    });

    Log::info('✅ Event created', [
      'event_id'          => $event->id,
      'user_id'           => auth()->id(),
      'source_event_id'   => optional($sourceEvent)->id,
    ]);

    return redirect()
      ->route('admin.events.overview', $event)
      ->with('success', $sourceEvent ? 'Copied event saved successfully.' : 'Event created successfully.');
  }

  public function edit(Event $event)
  {
    $eventTypes = EventType::orderBy('type')->get();
    $users = User::orderBy('name')->get();
    $adminIds = $event->admins()->pluck('users.id')->toArray();

    $event->load(['series', 'categoryEvents.category']);
    $mastersBatch = null;
    $mastersReadiness = null;
    if ($event->isMasters()) {
      $mastersBatch = \App\Models\MastersInvitationBatch::where('event_id', $event->id)->latest('id')->first();
      if ($mastersBatch) {
        $mastersReadiness = app(\App\Services\Masters\MastersInvitationService::class)->readiness($mastersBatch);
      }
    }

    return view('backend.event.edit', compact(
      'event', 'eventTypes', 'users', 'adminIds', 'mastersBatch', 'mastersReadiness'
    ));
  }





  public function update(Request $request, Event $event)
  {
    if ($request->exists('pathway_level')) $this->authorize('event-category.manage', $event);
    Log::info('🛠 Event update START', [
      'event_id' => $event->id,
      'user_id' => auth()->id(),
    ]);

    $data = $request->validate([
      'name' => 'required|string|max:255',
      'start_date' => 'nullable|date',
      'end_date' => 'nullable|date|after_or_equal:start_date',
      'information' => 'nullable|string',
      'venue_notes' => 'nullable|string',
      'entryFee' => 'nullable|integer',
      'deadline' => 'nullable|integer',
      'withdrawal_deadline' => 'nullable|date',
      'eventType' => 'required|integer|exists:eventtypes,id',
      'email' => 'nullable|email',
      'organizer' => 'nullable|string|max:191',
      'pathway_level' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Services\Performance\EventPathwayClassification::HOSTED_LEVELS))],

      'logo_existing' => 'nullable|string',
      'logo_upload' => 'nullable|image|max:2048',

      'admins' => 'nullable|array',
      'admins.*' => 'integer|exists:users,id',
    ]);

    app(\App\Services\EventFormatTransition::class)->assertAllowed($event, (int) $data['eventType']);

    /*
    |--------------------------------------------------------------------------
    | LOGO HANDLING
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | UPDATE FIELDS
    |--------------------------------------------------------------------------
    */

    $event->update([
      'name' => $data['name'],
      'start_date' => $data['start_date'] ?? null,
      'end_date' => $data['end_date'] ?? null,
      'information' => $data['information'] ?? null,
      'venue_notes' => $data['venue_notes'] ?? null,
      'pathway_level' => array_key_exists('pathway_level', $data) ? $data['pathway_level'] : $event->pathway_level,
      'entryFee' => $data['entryFee'] ?? null,
      'deadline' => $data['deadline'] ?? null,
      'withdrawal_deadline' => $data['withdrawal_deadline'] ?? null,
      'eventType' => $data['eventType'],
      'email' => $data['email'] ?? null,
      'organizer' => $data['organizer'] ?? null,
      'published' => $request->boolean('published'),
      'signUp' => $request->boolean('signUp'),
    ]);

    /*
    |--------------------------------------------------------------------------
    | ADMINS SYNC (ALWAYS)
    |--------------------------------------------------------------------------
    */

    $event->admins()->sync($request->input('admins', []));

    Log::info('✅ Event update COMPLETE', [
      'event_id' => $event->id,
    ]);

    return response()->json([
      'success' => true,
      'message' => 'Event updated successfully'
    ]);
  }

  public function copyEvent(Event $event)
  {
    if (!auth()->user()->hasRole('super-user')) {
      abort(403, 'Only super users can duplicate events.');
    }

    $eventTypes = EventType::orderBy('name')->get();
    $users = User::orderBy('name')->get();
    $adminIds = $event->admins()->pluck('users.id')->toArray();

    return view('backend.event.create', [
      'event'       => $event,
      'eventTypes'  => $eventTypes,
      'users'       => $users,
      'adminIds'    => $adminIds,
      'sourceEvent' => $event,
      'isCopy'      => true,
      'logoFiles'   => $this->availableEventLogos(),
    ]);
  }

  private function availableEventLogos(): array
  {
    $directory = public_path('assets/img/logos');

    if (! File::isDirectory($directory)) {
      return [];
    }

    return collect(File::files($directory))
      ->filter(fn ($file) => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true))
      ->map(fn ($file) => $file->getFilename())
      ->sort(SORT_NATURAL | SORT_FLAG_CASE)
      ->values()
      ->all();
  }

}
