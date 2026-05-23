<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\CategoryEvent;
use App\Models\Registration;
use App\Models\CategoryEventRegistration;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\Player;
use App\Models\PlayerRegistration;
use Maatwebsite\Excel\Facades\Excel;

use App\Mail\BulkEventMail;
use App\Exports\EventEntriesExport;
use App\Exports\CategoryEntriesExport;

  use App\Models\Transaction;
  use Illuminate\Support\Facades\DB;

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
        'allCategoryEventRegistrations' => function ($query) {
            $query->where('payment_status_id', 1)->with('registration.players');
        },
      ])
      ->get();

    return view('backend.event.individual.entries', compact('event', 'categoryEvents'));
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
      'registration_id' => ['required', 'exists:players,id'], // player_id
    ]);

    $playerId = $data['registration_id'];

    // 1️⃣ Prevent duplicate player in this category
    // Only block if the player has an active, paid entry (payment_status_id = 1).
    // Withdrawn entries and abandoned unpaid registrations should not block re-entry.
    $alreadyInCategory = $categoryEvent->categoryEventRegistrations()
      ->where('status', 'active')
      ->where('payment_status_id', 1)
      ->whereHas('registration.players', function ($q) use ($playerId) {
        $q->where('players.id', $playerId);
      })
      ->exists();

    if ($alreadyInCategory) {
      return response()->json([
        'success' => false,
        'message' => 'Player already in category',
      ], 422);
    }

    // 2️⃣ Create registration
    $registration = Registration::create([]);

    // 3️⃣ Attach player to registration
    PlayerRegistration::create([
      'player_id' => $playerId,
      'registration_id' => $registration->id,
    ]);

    // 4️⃣ Attach registration to category
    $entry = $categoryEvent->categoryEventRegistrations()->create([
      'registration_id' => $registration->id,
      'user_id'         => auth()->id(),
      'status'          => 'active',
      'payment_status_id' => 1,
      'payfast_id'      => 'Admin',
    ]);

    $entry->load('registration.players');

    // 5️⃣ Create an admin-entry transaction record so it appears in the ledger
    DB::table('transactions_pf')->insert([
      'created_at'        => now(),
      'updated_at'        => now(),
      'transaction_type'  => 'Registration',
      'amount_gross'      => 0.00,
      'amount_net'        => 0.00,
      'amount_fee'        => 0.00,
      'cape_tennis_fee'   => 15.00,
      'event_id'          => $categoryEvent->event_id,
      'player_id'         => $playerId,
      'category_event_id' => $categoryEvent->id,
      'pf_payment_id'     => null,
      'is_test'           => 0,
      'item_name'         => 'Admin Entry',
      'custom_int3'       => $categoryEvent->event_id,
      'custom_int4'       => auth()->id(),
      'custom_str1'       => optional($categoryEvent->category)->name,
      'custom_str3'       => optional($categoryEvent->event)->name,
    ]);

    return response()->json([
      'success' => true,
      'count' => $categoryEvent->activeRegistrations()->count(),
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

    $cer = $categoryEvent->categoryEventRegistrations()
      ->where('registration_id', $registration->id)
      ->first();

    if (!$cer) {
      return response()->json(['success' => false, 'message' => 'Registration not found'], 404);
    }

    $cer->markWithdrawn(auth()->user(), 'admin');

    return response()->json([
      'success' => true,
      'count' => $categoryEvent->activeRegistrations()->count(),
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

      $categoryEvent = CategoryEvent::with([
        'categoryEventRegistrations' => function ($query) {
            $query->where('payment_status_id', 1)->with('registration.players');
        }
      ])->findOrFail($data['category_event_id']);

      $emails = $categoryEvent->categoryEventRegistrations
        ->flatMap(fn($r) => $r->registration->players)
        ->pluck('email');

      Log::info('📍 Scope: category', [
        'category_event_id' => $categoryEvent->id,
        'registrations' => $categoryEvent->categoryEventRegistrations->pluck('registration_id'),
      ]);

    } else {

      $event = Event::with(['registrations' => function ($query) {
          $query->whereHas('categoryEventRegistrations', function ($q) {
              $q->where('payment_status_id', 1);
          })->with('players');
      }])->findOrFail($data['event_id']);

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
    return Player::query()
      ->orderBy('name')
      ->orderBy('surname')
      ->get()
      ->map(function ($player) {
        return [
          'id' => $player->id,   // now this is player_id
          'name' => trim($player->name . ' ' . $player->surname),
        ];
      });
  }

  public function movePlayer(Request $request, $entryId)
  {
    $entry = \App\Models\CategoryEventRegistration::findOrFail($entryId);

    $request->validate([
      'new_category_id' => ['required', 'exists:category_events,id']
    ]);

    $newCategory = CategoryEvent::findOrFail($request->new_category_id);

    if ($newCategory->isLocked()) {
      return response()->json([
        'success' => false,
        'message' => 'Target category is locked'
      ], 403);
    }

    // Prevent duplicate in target category
    $exists = $newCategory->categoryEventRegistrations()
      ->where('registration_id', $entry->registration_id)
      ->exists();

    if ($exists) {
      return response()->json([
        'success' => false,
        'message' => 'Player already in that category'
      ], 422);
    }

    // 🔥 Move by updating foreign key
    $entry->update([
      'category_event_id' => $newCategory->id
    ]);

    $entry->load('registration.players');

    return response()->json([
      'success' => true,
      'row' => view('backend.event.partials.entry-row', [
        'reg' => $entry,
      ])->render(),
    ]);
  }

  /**
   * Return provenance/detail data for a single entry (superadmin only).
   */
  public function entryDetails(CategoryEventRegistration $entry)
  {
    if (! auth()->user()->hasRole('super-user')) {
      return response()->json(['error' => 'Forbidden'], 403);
    }

    $entry->load(['registration.players', 'categoryEvent.category', 'categoryEvent.event']);

    $player = optional($entry->registration?->players)->first();
    $category = optional($entry->categoryEvent?->category);
    $event = optional($entry->categoryEvent?->event);

    // User who created/paid this CER
    $addedByUser = $entry->user_id
      ? DB::table('users')->where('id', $entry->user_id)->first(['id', 'userName', 'userSurname', 'email', 'userType'])
      : null;

    // Transaction(s) linked to this entry
    $transactions = DB::table('transactions_pf')
      ->where('category_event_id', $entry->category_event_id)
      ->where(function ($q) use ($player) {
          if ($player) $q->where('player_id', $player->id);
      })
      ->orderBy('created_at', 'desc')
      ->get(['id', 'pf_payment_id', 'amount_gross', 'amount_fee', 'amount_net',
             'cape_tennis_fee', 'is_test', 'item_name', 'name_first', 'name_last',
             'email_address', 'custom_int4', 'custom_str2', 'custom_str4', 'transaction_type', 'created_at']);

    // Wallet payment for this entry (via registration_order_items -> wallet_transactions)
    $walletPayment = null;
    $walletCreditBack = null;
    if ($player) {
      $orderItem = DB::table('registration_order_items')
        ->where('category_event_id', $entry->category_event_id)
        ->where('player_id', $player->id)
        ->first(['order_id', 'item_price']);

      if ($orderItem) {
        $walletPayment = DB::table('wallet_transactions as wt')
          ->join('wallets as w', 'w.id', '=', 'wt.wallet_id')
          ->where('wt.source_id', $orderItem->order_id)
          ->where('wt.source_type', 'event_registration_wallet_payment')
          ->where('wt.type', 'debit')
          ->first(['wt.id', 'wt.amount', 'wt.created_at', 'wt.meta', 'w.payable_type', 'w.payable_id']);
      }

      // Wallet refund credited back
      $walletCreditBack = DB::table('wallet_transactions')
        ->where('source_id', $entry->id)
        ->where('source_type', 'event_registration_refund')
        ->where('type', 'credit')
        ->first(['id', 'amount', 'created_at', 'meta']);
    }

    // Resolve "added by" from transaction custom_int4 or wallet payable if CER user_id is null
    $txAddedByUser = null;
    if (! $addedByUser) {
      $txUserId = $transactions->isNotEmpty() ? $transactions->first()->custom_int4 : null;
      // Fallback: wallet payable user
      if (! $txUserId && $walletPayment && $walletPayment->payable_type === 'App\Models\User') {
        $txUserId = $walletPayment->payable_id;
      }
      if ($txUserId) {
        $txAddedByUser = DB::table('users')->where('id', $txUserId)
          ->first(['id', 'userName', 'userSurname', 'email', 'userType']);
      }
    }

    $resolvedAddedBy = $addedByUser ?? $txAddedByUser;

    // Determine payment method
    $tx = $transactions->first();
    $paymentMethod = 'Unknown';
    if ($walletPayment && (! $tx || ! $tx->pf_payment_id)) {
      $paymentMethod = 'Wallet';
    } elseif ($tx) {
      if ($tx->is_test) {
        $paymentMethod = 'PayFast (TEST)';
      } elseif ($tx->pf_payment_id && $tx->amount_gross > 0 && $walletPayment) {
        $paymentMethod = 'PayFast + Wallet';
      } elseif ($tx->pf_payment_id && $tx->amount_gross > 0) {
        $paymentMethod = 'PayFast';
      } elseif ($tx->item_name === 'Admin Entry' || $tx->amount_gross == 0) {
        $paymentMethod = 'Admin Entry (private collection)';
      } else {
        $paymentMethod = $tx->item_name ?? 'Unknown';
      }
    } elseif ($entry->user_id && ! $tx) {
      $paymentMethod = 'Admin Entry (private collection)';
    }

    // Check if user 584 or any super-admin role added this
    $superAdminUserIds = DB::table('model_has_roles')
      ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
      ->whereIn('roles.name', ['super-user', 'super-admin', 'superadmin', 'Super Admin'])
      ->pluck('model_has_roles.model_id')
      ->toArray();

    $addedByRole = 'Unknown';
    if ($resolvedAddedBy) {
      if (in_array($resolvedAddedBy->id, $superAdminUserIds) || ($resolvedAddedBy->userType ?? '') === 'superAdmin') {
        $addedByRole = 'Super Admin';
      } else {
        $addedByRole = 'User / Event Admin';
      }
    }

    return response()->json([
      'entry_id'        => $entry->id,
      'player'          => $player ? "{$player->name} {$player->surname}" : 'Unknown',
      'player_id'       => $player?->id,
      'player_email'    => $player?->email,
      'player_cell'     => $player?->cellNr,
      'category'        => $category?->name ?? '—',
      'event'           => $event?->name ?? '—',
      'entry_status'    => $entry->status,
      'payment_status'  => $entry->payment_status_id == 1 ? 'Paid' : 'Unpaid',
      'payment_method'  => $paymentMethod,
      'pf_transaction_id' => $entry->pf_transaction_id,
      'payfast_id'      => $entry->payfast_id,
      'created_at'      => $entry->created_at?->format('Y-m-d H:i:s'),
      'withdrawn_at'    => $entry->withdrawn_at,
      'refund_status'   => $entry->refund_status,
      'refund_method'   => $entry->refund_method,
      'refund_gross'    => $entry->refund_gross,
      'wallet_payment'  => $walletPayment ? [
        'wt_id'       => $walletPayment->id,
        'amount'      => $walletPayment->amount,
        'created_at'  => $walletPayment->created_at,
        'meta'        => $walletPayment->meta,
        'payable'     => $walletPayment->payable_type . ':' . $walletPayment->payable_id,
      ] : null,
      'wallet_refund'   => $walletCreditBack ? [
        'wt_id'       => $walletCreditBack->id,
        'amount'      => $walletCreditBack->amount,
        'created_at'  => $walletCreditBack->created_at,
        'meta'        => $walletCreditBack->meta,
      ] : null,
      'added_by' => $resolvedAddedBy ? [
        'id'       => $resolvedAddedBy->id,
        'name'     => trim(($resolvedAddedBy->userName ?? '') . ' ' . ($resolvedAddedBy->userSurname ?? '')),
        'email'    => $resolvedAddedBy->email ?? '',
        'userType' => $resolvedAddedBy->userType ?? '',
        'role'     => $addedByRole,
      ] : null,
      'transactions' => $transactions->map(fn($t) => [
        'id'            => $t->id,
        'pf_payment_id' => $t->pf_payment_id,
        'amount_gross'  => $t->amount_gross,
        'amount_fee'    => $t->amount_fee,
        'amount_net'    => $t->amount_net,
        'cape_tennis_fee' => $t->cape_tennis_fee,
        'is_test'       => $t->is_test,
        'item_name'     => $t->item_name,
        'name_first'    => $t->name_first,
        'name_last'     => $t->name_last,
        'payer_name'    => $t->custom_str2 ?: trim(($t->name_first ?? '') . ' ' . ($t->name_last ?? '')),
        'email_address' => $t->email_address ?: ($t->custom_str4 ?? null),
        'created_at'    => $t->created_at,
      ])->values(),
    ]);
  }

}
