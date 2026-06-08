<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\CategoryEvent;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;

class EventResultsController extends Controller
{
  public function individual(Event $event)
  {
    // ── 1. All categories + their category name (2 queries) ──────────────────
    $categoryEvents = CategoryEvent::where('event_id', $event->id)
      ->with('category')
      ->get();

    if ($categoryEvents->isEmpty()) {
      return view('backend.event.results.individual', [
        'event'      => $event,
        'categories' => collect(),
      ]);
    }

    $categoryEventIds = $categoryEvents->pluck('id')->all();

    // ── 2. Qualifying registration IDs ───────────────────────────────────────
    // Path A: paid via order (payfast or wallet)
    $paidViaOrder = DB::table('registrations')
      ->join('category_event_registrations as cer', 'registrations.id', '=', 'cer.registration_id')
      ->join('registration_order_items as roi', 'registrations.id', '=', 'roi.registration_id')
      ->join('registration_orders as ro', 'roi.order_id', '=', 'ro.id')
      ->leftJoin('transactions_pf as tpf', 'cer.pf_transaction_id', '=', 'tpf.id')
      ->whereIn('cer.category_event_id', $categoryEventIds)
      ->where('cer.status', '!=', 'withdrawn')
      ->where(function ($w) {
        $w->where('ro.payfast_paid', 1)
          ->orWhere('ro.wallet_debited', '>', 0);
      })
      ->where(function ($w) {
        // exclude registrations whose payfast transaction is a test
        $w->whereNull('tpf.id')
          ->orWhere('tpf.is_test', '!=', 1);
      })
      ->select('registrations.id')
      ->distinct()
      ->pluck('id');

    // Path B: admin-approved entries (payment_status_id = 1, with or without an order row)
    $paidByAdmin = DB::table('registrations')
      ->join('category_event_registrations as cer', 'registrations.id', '=', 'cer.registration_id')
      ->whereIn('cer.category_event_id', $categoryEventIds)
      ->where('cer.status', '!=', 'withdrawn')
      ->where('cer.payment_status_id', 1)
      ->select('registrations.id')
      ->distinct()
      ->pluck('id');

    $qualifyingIds = $paidViaOrder->merge($paidByAdmin)->unique()->values()->all();

    if (empty($qualifyingIds)) {
      $categories = $categoryEvents->map(function ($cat) {
        $cat->setRelation('registrations', collect());
        return $cat;
      });

      return view('backend.event.results.individual', compact('event', 'categories'));
    }

    // ── 3. All saved positions for this event (1 query) ───────────────────────
    $savedPositions = DB::table('category_results')
      ->where('event_id', $event->id)
      ->whereIn('registration_id', $qualifyingIds)
      ->select('category_id', 'registration_id', 'position')
      ->get()
      ->groupBy('category_id')           // keyed by category_id
      ->map(fn($rows) => $rows->keyBy('registration_id'));

    // ── 4. All registrations with players eager-loaded (2 queries) ────────────
    $registrations = Registration::whereIn('id', $qualifyingIds)
      ->with('players')
      ->get()
      ->keyBy('id');

    // ── 5. Per-category pivot: which registration belongs to which category,
    //       excluding withdrawn rows (1 query) ─────────────────────────────────
    $pivotRows = DB::table('category_event_registrations')
      ->whereIn('category_event_id', $categoryEventIds)
      ->whereIn('registration_id', $qualifyingIds)
      ->where('status', '!=', 'withdrawn')
      ->select('category_event_id', 'registration_id')
      ->get()
      ->groupBy('category_event_id');

    // ── 6. Assemble per-category in PHP (no more queries) ────────────────────
    $categories = $categoryEvents->map(function ($category) use ($registrations, $savedPositions, $pivotRows) {
      $categoryResults = $savedPositions->get($category->category_id, collect());
      $hasSavedResults = $categoryResults->isNotEmpty();

      $catRegistrations = $pivotRows
        ->get($category->id, collect())
        ->pluck('registration_id')
        ->map(fn($id) => $registrations->get($id))
        ->filter()
        ->map(function ($reg) use ($categoryResults, $hasSavedResults) {
          // If no results have been saved yet, treat all players as positioned (not removed)
          $reg->position = $hasSavedResults
            ? ($categoryResults->get($reg->id)?->position ?? null)
            : -1; // -1 = unsaved default (not removed)
          return $reg;
        })
        ->sortBy([
          fn($a, $b) => (int) ($a->position === null) - (int) ($b->position === null),
          fn($a, $b) => ($a->position === null ? PHP_INT_MAX : ($a->position < 0 ? PHP_INT_MAX - 1 : $a->position))
                    <=> ($b->position === null ? PHP_INT_MAX : ($b->position < 0 ? PHP_INT_MAX - 1 : $b->position)),
          fn($a, $b) => $a->id <=> $b->id,
        ])
        ->values();

      $category->setRelation('registrations', $catRegistrations);

      return $category;
    });

    return view('backend.event.results.individual', compact('event', 'categories'));
  }
}
