<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\CategoryEvent;
use App\Models\Registration;
use App\Models\CategoryEventRegistration;
use App\Models\MastersInvitationBatch;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\Player;
use App\Models\PlayerRegistration;
use Maatwebsite\Excel\Facades\Excel;

use App\Mail\BulkEventMail;
use App\Exports\EventEntriesExport;
use App\Exports\CategoryEntriesExport;
use App\Services\BulkMailDispatcher;

  use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Domain\Entries\Services\EntryService;
use App\Domain\Payments\Services\UnpaidCheckoutExpiryService;
use App\Models\RegistrationOrderItems;

class EventEntryController extends Controller
{
  public function __construct(private EntryService $entryService) {}
  /**
   * Show entries page (grouped per category).
   */
  public function index(Event $event)
  {
    $this->authorize('event-draw.view', $event);

    $showPendingCheckouts = auth()->user()->hasRole('super-user') && !$event->isInterprovincialTrials();
    if ($showPendingCheckouts) {
      app(UnpaidCheckoutExpiryService::class)->expireRegistrationForEvent((int) $event->id);
    }

    $categoryEvents = $event->eventCategories()
      ->with([
        'category',
        'allCategoryEventRegistrations' => function ($query) {
            $query->activeAndPaid()->with('registration.players');
        },
      ])
      ->get();

    $pendingCheckouts = collect();
    $pendingCheckoutClocks = [];
    if ($showPendingCheckouts) {
      $pendingCheckouts = CategoryEventRegistration::query()
        ->active()
        ->whereHas('categoryEvent', fn ($query) => $query->where('event_id', $event->id))
        ->where(function ($query) {
          $query->where('payment_status_id', '!=', 1)
            ->orWhereNull('payment_status_id');
        })
        ->with(['categoryEvent.category', 'registration.players'])
        ->latest('id')
        ->limit(100)
        ->get();

      if ($pendingCheckouts->isNotEmpty()) {
        $items = RegistrationOrderItems::query()->where(function ($query) use ($pendingCheckouts) {
          foreach ($pendingCheckouts as $entry) {
            $query->orWhere(fn ($pair) => $pair->where('category_event_id', $entry->category_event_id)
              ->where('registration_id', $entry->registration_id));
          }
        })->with('order:id,created_at,status,pay_status,payfast_paid,wallet_debited,payfast_pf_payment_id,wallet_transaction_id')->orderBy('id')->limit(1001)->get();
        $grouped = $items->groupBy(fn ($item) => $item->category_event_id.':'.$item->registration_id);
        foreach ($pendingCheckouts as $entry) {
          $matches = $grouped->get($entry->category_event_id.':'.$entry->registration_id, collect());
          $orderIds = $matches->pluck('order_id')->unique();
          $order = $items->count() <= 1000 && $orderIds->count() === 1 ? $matches->first()->order : null;
          $clock = [
            'created_at' => $order?->created_at,
            'review' => $items->count() > 1000 ? 'Checkout records need review'
              : ($orderIds->count() > 1 ? 'Multiple checkouts — review required'
              : (!$order ? 'No matching checkout — review required'
              : (!$order->created_at ? 'Checkout time missing — review required' : null))),
          ];
          $clock['payment_label'] = $order?->pay_status ? 'Paid order — entry needs review'
            : ($order?->status === 'cancelled' ? 'Cancelled checkout — entry needs review'
            : (($order?->payfast_paid || $order?->wallet_debited || filled($order?->payfast_pf_payment_id) || filled($order?->wallet_transaction_id))
              ? 'Payment evidence — review required' : ($clock['review'] ? 'Review required' : 'Pending')));
          $clock['payment_pending'] = $clock['payment_label'] === 'Pending';
          $pendingCheckoutClocks[$entry->id] = $clock;
        }
      }
    }

    $mastersBatch = $event->isMasters()
      ? MastersInvitationBatch::query()
          ->where('event_id', $event->id)
          ->where('status', '!=', 'restarted')
          ->latest('id')
          ->first()
      : null;

    return view('backend.event.individual.entries', compact('event', 'categoryEvents', 'mastersBatch', 'pendingCheckouts', 'pendingCheckoutClocks'));
  }

  /**
   * Lock a category.
   */
  public function lock(CategoryEvent $categoryEvent)
  {
    $this->authorize('category.manage', $categoryEvent);
    $this->rejectMastersRosterMutation($categoryEvent->event);

    $this->entryService->lockCategory($categoryEvent, auth()->user());

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
    $this->authorize('category.manage', $categoryEvent);
    $this->rejectMastersRosterMutation($categoryEvent->event);

    $this->entryService->unlockCategory($categoryEvent, auth()->user());

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
    $this->authorize('event-draw.view', $categoryEvent->event);
    $this->rejectMastersRosterMutation($categoryEvent->event);

    $data = $request->validate([
      'registration_id' => ['required', 'exists:players,id'],
      'collection_status' => ['sometimes', Rule::in(['unpaid', 'paid_privately'])],
      'existing_order_id' => ['sometimes', 'required', 'integer', 'min:1'],
      'confirmed_amount' => ['required_with:existing_order_id', 'numeric', 'decimal:0,2', 'gt:0'],
      'reference' => ['required_with:existing_order_id', 'string', 'min:3', 'max:120'],
    ]);

    $playerId = $data['registration_id'];

    try {
      $recovery = null;
      if ($categoryEvent->event->isInterprovincialTrials() && ($data['collection_status'] ?? '') === 'paid_privately') {
        $recovery = app(\App\Services\InterprovincialTrials\EntriesManualPaymentService::class)
          ->resolve($categoryEvent, (int) $playerId, $request->user(), $data);
        if (isset($recovery['checkout'])) {
          return response()->json([
            'success' => false,
            'message' => 'This player has an existing checkout. Confirm the payment received against that checkout.',
            'existing_checkout' => $recovery['checkout'],
          ], 409);
        }
      } elseif (isset($data['existing_order_id'])) {
        return response()->json(['success' => false, 'message' => 'Checkout confirmation is only available for a Trials payment received outside the app.'], 422);
      }
      $entry = $recovery['entry'] ?? $this->entryService->addPlayerAsAdmin(
        $categoryEvent,
        $playerId,
        auth()->user(),
        $data['collection_status'] ?? 'unpaid'
      );
    } catch (\RuntimeException $e) {
      $status = str_contains($e->getMessage(), 'locked') ? 403 : 422;
      return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
    }

    $entry->load('registration.players');

    return response()->json([
      'success' => true,
      'count' => $categoryEvent->activeRegistrations()->count(),
      'admin_payment_status' => $entry->admin_payment_status,
      'existing_checkout_settled' => isset($recovery['entry']),
      'entry_id' => $entry->id,
      'row' => view('backend.event.partials.entry-row', [
        'reg' => $entry,
      ])->render(),
    ]);
  }

  /**
   * Toggle the non-reconciled private-collection note on an admin-created entry.
   * This never changes payment_status_id or any financial ledger record.
   */
  public function updateAdminPaymentStatus(Request $request, CategoryEventRegistration $entry)
  {
    $categoryEvent = $entry->categoryEvent;
    abort_unless($categoryEvent, 404);
    $this->authorize('event-draw.view', $categoryEvent->event);
    $this->rejectMastersRosterMutation($categoryEvent->event);

    $data = $request->validate([
      'paid' => ['required', 'boolean'],
    ]);

    try {
      $entry = $this->entryService->setAdminPaymentStatus(
        $entry,
        (bool) $data['paid'],
        $request->user()
      );
    } catch (\RuntimeException $e) {
      return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
    }

    $result = [
      'success' => true,
      'admin_payment_status' => $entry->admin_payment_status,
      'canonical_payment_status' => $entry->is_paid ? 'paid' : 'unpaid',
    ];

    if (! $request->expectsJson()) {
      return back()->with(
        'success',
        "Private collection note marked {$entry->admin_payment_status}; no payment was reconciled."
      );
    }

    return response()->json($result);
  }

  /**
   * Remove a registration from a category.
   */
  public function removePlayer(CategoryEvent $categoryEvent, Registration $registration)
  {
    $this->authorize('category.manage', $categoryEvent);

    if ($categoryEvent->event?->isMasters()) {
      return response()->json([
        'success' => false,
        'message' => 'Withdraw Masters players from Confirmed Entries so invitation, replacement and refund handling stay synchronized.',
      ], 422);
    }

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

    app(\App\Domain\Entries\Services\EntryService::class)
      ->withdrawEntryAsAdmin($cer, auth()->user());

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
    $this->authorize('event-draw.view', $event);

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
    $this->authorize('category.manage', $categoryEvent);

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
      'category_event_id' => 'nullable|required_if:scope,category|exists:category_events,id',
      'registration_id' => 'nullable|required_if:scope,player|exists:registrations,id',

      'subject' => 'required|string|max:255',
      'message' => 'required|string',

      'campaign_key' => 'nullable|uuid',
      'from_name' => 'required|string|max:100',
      'reply_to' => 'required|email|max:255',
    ]);

    // Authorize via the parent event
    $authEvent = Event::findOrFail($data['event_id']);
    $this->authorize('event-draw.view', $authEvent);
    abort_unless(app(\App\Services\EventCommunicationService::class)->managesWholeEvent($authEvent,$request->user()),403);
    if ($data['scope']==='player') {
      Registration::whereKey($data['registration_id'])->whereHas('categoryEventRegistrations', function ($entries) use ($authEvent) {
        $entries->where(fn ($status) => $status->whereNull('status')->orWhere(fn ($active) => $active->active()))
          ->whereNull('withdrawn_at')->whereHas('categoryEvent', fn ($category) => $category->where('event_id', $authEvent->id));
      })->firstOrFail();
    } elseif ($data['scope']==='category') {
      CategoryEvent::where('event_id',$authEvent->id)->findOrFail($data['category_event_id']);
    }
    $options = ['scope'=>'legacy_registered', 'filter'=>'all', 'registration_id'=>$data['scope']==='player' ? $data['registration_id'] : null, 'category_event_id'=>$data['scope']==='category' ? $data['category_event_id'] : null, 'recipients'=>'players']
      + \App\Services\CommunicationSender::resolve($data, $request->user());
    $body = trim(strip_tags(preg_replace('/<\/(p|div|li)>|<br\s*\/?\s*>/i',"\n",$data['message'])));
    $batch = app(\App\Services\EventCommunicationService::class)->preview($authEvent, $request->user(), $options, $data['subject'], $body);
    $url = route('backend.event-communications.review', [$authEvent, $batch]);
    if ($request->expectsJson()) return response()->json(['success'=>true,'review_required'=>true,'review_url'=>$url]);
    return redirect($url);

  }


  public function availableRegistrations(Request $request, CategoryEvent $categoryEvent)
  {
    $this->authorize('event-draw.view', $categoryEvent->event);
    $this->rejectMastersRosterMutation($categoryEvent->event);

    $data = $request->validate([
      'q' => ['nullable', 'string', 'max:100'],
      'page' => ['nullable', 'integer', 'min:1'],
    ]);
    $search = trim((string) ($data['q'] ?? ''));
    $page = (int) ($data['page'] ?? 1);

    $players = Player::query()
      ->select(['players.id', 'players.name', 'players.surname'])
      ->whereNotExists(function ($query) use ($categoryEvent) {
        $query->selectRaw('1')
          ->from('player_registrations')
          ->join('category_event_registrations', 'category_event_registrations.registration_id', '=', 'player_registrations.registration_id')
          ->whereColumn('player_registrations.player_id', 'players.id')
          ->where('category_event_registrations.category_event_id', $categoryEvent->id)
          ->where('category_event_registrations.payment_status_id', 1)
          ->where('category_event_registrations.status', '!=', 'withdrawn');
      })
      ->when($search !== '', function ($query) use ($search) {
        foreach (preg_split('/\s+/', $search) as $term) {
          $escaped = addcslashes($term, '\\%_');
          $query->where(function ($nameQuery) use ($escaped) {
            $nameQuery->where('players.name', 'like', "%{$escaped}%")
              ->orWhere('players.surname', 'like', "%{$escaped}%");
          });
        }
      })
      ->orderBy('players.surname')
      ->orderBy('players.name')
      ->orderBy('players.id')
      ->simplePaginate(20, ['players.id', 'players.name', 'players.surname'], 'page', $page);

    return response()->json([
      'results' => collect($players->items())->map(function (Player $player) {
        return [
          'id' => $player->id,
          'text' => trim($player->name . ' ' . $player->surname) . ' · profile #' . $player->id,
        ];
      })->values(),
      'pagination' => ['more' => $players->hasMorePages()],
    ]);
  }

  public function movePlayer(Request $request, $entryId)
  {
    $entry = \App\Models\CategoryEventRegistration::findOrFail($entryId);

    // Authorize via the source category event
    if ($entry->categoryEvent) {
      $this->authorize('category.manage', $entry->categoryEvent);
    }

    abort_unless($entry->categoryEvent?->event, 404);
    $this->rejectMastersRosterMutation($entry->categoryEvent->event);

    $request->validate([
      'new_category_id' => ['required', 'exists:category_events,id']
    ]);

    $newCategory = CategoryEvent::findOrFail($request->new_category_id);

    if ((int) $newCategory->event_id !== (int) $entry->categoryEvent->event_id) {
      return response()->json([
        'success' => false,
        'message' => 'Players can only be moved between categories in the same event.',
      ], 422);
    }

    $this->rejectMastersRosterMutation($newCategory->event);
    $this->authorize('category.manage', $newCategory);

    if (! $entry->is_paid && $request->boolean('preview')) {
      return response()->json(['success' => true, 'legacy_unpaid' => true, 'wallet_credit' => 0]);
    }
    if ($entry->is_paid) {
      $adjustments = app(\App\Domain\Entries\Services\CategoryAdjustmentService::class);
      if ($request->boolean('preview')) {
        $quote = $adjustments->quote($entry, $newCategory, $request->user(), true);
        if ($quote['difference'] > 0) {
          return response()->json(['success' => false, 'message' => 'The registering account must change this category and pay the difference through PayFast.'], 422);
        }
        return response()->json($quote + ['success' => true]);
      }
      $request->validate(['quote_token' => ['required', 'string'], 'request_token' => ['required', 'uuid']]);
      $adjustment = $adjustments->start($entry, $newCategory, $request->user(), $request->quote_token, $request->request_token, true);
      return response()->json(['success' => true,
        'message' => (float) $adjustment->wallet_credit > 0 ? 'Category changed; difference credited to the original payer wallet.' : 'Category changed.',
        'row' => view('backend.event.partials.entry-row', ['reg' => $entry->fresh()->load('registration.players')])->render(),
      ]);
    }

    if ($entry->categoryEvent->draws()->exists() || $newCategory->draws()->exists()) {
      return response()->json(['success' => false, 'message' => 'Players can only move before draw creation.'], 422);
    }

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
    if ($entry->categoryEvent->event->isInterprovincialTrials()) {
      app(\App\Services\InterprovincialTrials\InvitationService::class)->transferEntry($entry, $newCategory, $request->user());
    } else {
      $entry->update(['category_event_id' => $newCategory->id]);
    }

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
    abort_unless(request()->user()?->hasRole('super-user'), 403);

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
             'cape_tennis_fee', 'is_test', 'item_name',
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
      'admin_payment_status' => $entry->admin_payment_status,
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
        'name_first'    => null,
        'name_last'     => null,
        'payer_name'    => $t->custom_str2 ?: null,
        'email_address' => $t->email_address ?: ($t->custom_str4 ?? null),
        'created_at'    => $t->created_at,
      ])->values(),
    ]);
  }

  private function rejectMastersRosterMutation(?Event $event): void
  {
    abort_if(
      $event?->isMasters(),
      422,
      'Manage Masters invitations, reserves, categories and private payments from the Masters dashboard.'
    );
  }

}
