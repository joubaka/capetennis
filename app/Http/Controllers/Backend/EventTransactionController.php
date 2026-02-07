<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Transaction;

class EventTransactionController extends Controller
{
  /**
   * Display tournament transactions & income breakdown
   */
  public function index(Event $event)
  {
    // =========================
    // CONFIG
    // =========================
    $feePerEntry = $event->cape_tennis_fee; // Cape Tennis fee per entry (player or team)
    $isTeamEvent = $event->isTeam();

    // =========================
    // PAYFAST TRANSACTIONS
    // =========================
    $transactions = Transaction::with([
      'user',
      'order.items.player',
      'order.items.category_event',
    ])
      ->where('event_id', $event->id)
      ->where('amount_gross', '>', 0)
      ->orderBy('created_at', 'desc')
      ->get();

    // =========================
    // CATEGORY STATS
    // =========================
    $categoryStats = $transactions
      ->flatMap(fn($t) => optional($t->order)->items ?? collect())
      ->groupBy('category_event_id')
      ->map(function ($items) use ($feePerEntry, $isTeamEvent) {

        // Count entries correctly per event type
        $entries = $isTeamEvent
          ? $items->pluck('order_id')->unique()->count() // teams
          : $items->count(); // players
  
        $gross = $items->sum('item_price');
        $siteFee = $entries * $feePerEntry;

        return [
          'category' => optional($items->first()->category_event->category)->name ?? 'Unknown',
          'entries' => $entries,
          'gross' => $gross,
          'site_fee' => $siteFee,
          'net' => $gross - $siteFee,
        ];
      })
      ->sortByDesc('gross')
      ->values();

    // =========================
    // TOTALS
    // =========================
    $totalGross = $transactions->sum('amount_gross');

    $totalPayfastFees = $transactions->sum(
      fn($t) => abs($t->amount_fee ?? 0)
    );

    // Total entries (players or teams)
    $totalEntries = $isTeamEvent
      ? $transactions->count() // teams
      : $transactions
        ->flatMap(fn($t) => optional($t->order)->items ?? collect())
        ->count(); // players

    $totalCapeTennisFees = $totalEntries * $feePerEntry;

    $netTournamentIncome =
      $totalGross
      - $totalPayfastFees
      - $totalCapeTennisFees;

    // =========================
    // VIEW
    // =========================
    return view('backend.event.transactions', [
      'event' => $event,
      'transactions' => $transactions,
      'feePerEntry' => $feePerEntry,
      'isTeamEvent' => $isTeamEvent,
      'totalEntries' => $totalEntries,
      'categoryStats' => $categoryStats,

      'totals' => [
        'gross' => $totalGross,
        'payfast_fees' => $totalPayfastFees,
        'site_fees' => $totalCapeTennisFees,
        'net' => $netTournamentIncome,
      ],
    ]);
  }

}
