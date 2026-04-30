<?php

namespace App\Http\Controllers\Frontend;

use App\Services\Payfast;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\ClothingOrder;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Agreement;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Registration;
use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\SellProduct;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\Transaction;
use App\Models\User;
use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use stdClass;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;


class RegisterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        dd('index');
    }

  /**
   * Validate PayFast ITN signature.
   * Accepts optional Request because some callers pass it and some call without.
   */
  private function validatePayfastSignature(Request $request = null)
  {
    // Support both direct $_POST and injected Request
    $data = [];
    if ($request instanceof Request) {
      $data = $request->all();
    } elseif (!empty($_POST)) {
      $data = $_POST;
    }

    $incoming = $data['signature'] ?? null;
    if (empty($incoming)) {
      Log::warning('[PAYFAST SIG] Missing signature in ITN data');
      return false;
    }

    $merchantId = $data['merchant_id'] ?? null;

    // Check if any passphrase is configured
    $hasPassphrase = !empty(env('PAYFAST_PASSPHRASE_LIVE')) 
                  || !empty(env('PAYFAST_PASSPHRASE_SANDBOX'))
                  || !empty(env('PAYFAST_PASSPHRASE'));

    Log::info('[PAYFAST SIG] Start validation', [
      'merchant_id' => $merchantId,
      'has_passphrase' => $hasPassphrase,
    ]);

    // Build signature string according to PayFast spec: sort fields, exclude signature
    $fields = $data;
    unset($fields['signature']);

    ksort($fields);

    $parts = [];
    foreach ($fields as $k => $v) {
      if ($v === '' || $v === null) {
        continue;
      }
      $parts[] = $k . '=' . urlencode($v);
    }

    $string = implode('&', $parts);

    // If no passphrases configured, accept ITN without signature validation
    // (This is a fallback for production environments where passphrase wasn't configured)
    if (!$hasPassphrase) {
      Log::warning('[PAYFAST SIG] No passphrase configured - accepting ITN without validation', [
        'merchant_id' => $merchantId,
        'pf_payment_id' => $data['pf_payment_id'] ?? null,
      ]);
      return true;
    }

    // Determine which passphrase to try based on merchant_id
    $sandboxMerchantId = '10008657'; // From Payfast.php
    $liveMerchantId = '11307280';     // From Payfast.php

    $passphrases = [];

    // If merchant_id matches sandbox config, try sandbox passphrase first
    if ($merchantId && (string) $merchantId === (string) $sandboxMerchantId) {
      $sandboxPass = env('PAYFAST_PASSPHRASE_SANDBOX');
      if ($sandboxPass) {
        $passphrases[] = $sandboxPass;
        Log::info('[PAYFAST SIG] Trying sandbox passphrase for merchant', ['merchant_id' => $merchantId]);
      }
    }
    // If merchant_id matches live config, try live passphrase first
    elseif ($merchantId && (string) $merchantId === (string) $liveMerchantId) {
      $livePass = env('PAYFAST_PASSPHRASE_LIVE');
      if ($livePass) {
        $passphrases[] = $livePass;
        Log::info('[PAYFAST SIG] Trying live passphrase for merchant', ['merchant_id' => $merchantId]);
      }
    }

    // Add all other configured passphrases as fallback
    $livePass = env('PAYFAST_PASSPHRASE_LIVE');
    if ($livePass && !in_array($livePass, $passphrases)) {
      $passphrases[] = $livePass;
    }

    $sandboxPass = env('PAYFAST_PASSPHRASE_SANDBOX');
    if ($sandboxPass && !in_array($sandboxPass, $passphrases)) {
      $passphrases[] = $sandboxPass;
    }

    $genericPass = env('PAYFAST_PASSPHRASE');
    if ($genericPass && !in_array($genericPass, $passphrases)) {
      $passphrases[] = $genericPass;
    }

    Log::info('[PAYFAST SIG] Attempting signature validation', [
      'passphrases_count' => count($passphrases),
      'string_length' => strlen($string),
    ]);

    // Try each passphrase
    foreach ($passphrases as $i => $pf) {
      if (empty($pf)) {
        continue;
      }
      $calc = md5($string . '&passphrase=' . urlencode($pf));
      if ($calc === $incoming) {
        Log::info('[PAYFAST SIG] ✓ Valid signature matched', ['attempt' => $i + 1]);
        return true;
      }
    }

    // Fallback: try without passphrase
    if (md5($string) === $incoming) {
      Log::info('[PAYFAST SIG] ✓ Valid signature (no passphrase)');
      return true;
    }

    Log::error('[PAYFAST SIG] ✗ Signature validation failed', [
      'merchant_id' => $merchantId,
      'passphrases_tried' => count($passphrases),
      'received_sig' => substr($incoming, 0, 8) . '...',
    ]);

    return false;
  }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function create()
    {
        dd('index');
    }


  /**
   * AJAX endpoint for Select2 player search.
   */
  public function searchPlayers(Request $request)
  {
    $term = $request->get('q', '');

    $query = Player::select('id', 'name', 'surname')
      ->orderBy('name')
      ->orderBy('surname');

    if ($term !== '') {
      $query->where(function ($q) use ($term) {
        $q->where('name', 'LIKE', "%{$term}%")
          ->orWhere('surname', 'LIKE', "%{$term}%")
          ->orWhereRaw("CONCAT(name, ' ', surname) LIKE ?", ["%{$term}%"]);
      });
    }

    $players = $query->limit(50)->get();

    return response()->json([
      'results' => $players->map(fn($p) => [
        'id' => $p->id,
        'text' => $p->name . ' ' . $p->surname,
      ]),
    ]);
  }

  /**
   * AJAX endpoint to get player details for the confirm step.
   */
  public function getPlayerDetails(Request $request)
  {
    $request->validate(['player_id' => 'required|integer|exists:players,id']);

    $player = Player::findOrFail($request->player_id);

    // Only pre-fill date of birth if the profile was confirmed in 2026 or later
    $confirmedIn2026 = $player->profile_updated_at
      && $player->profile_updated_at->year >= 2026;

    return response()->json([
      'id'          => $player->id,
      'name'        => $player->name,
      'surname'     => $player->surname,
      'email'       => $player->email,
      'cellNr'      => $player->cellNr,
      'dateOfBirth' => $confirmedIn2026 ? $player->dateOfBirth : null,
      'gender'      => $player->gender == 1 ? 'Male' : ($player->gender == 2 ? 'Female' : ''),
    ]);
  }

  /**
   * AJAX endpoint to update player details from the confirm step.
   */
  public function updatePlayerDetails(Request $request)
  {
    $validated = $request->validate([
      'player_id'   => 'required|integer|exists:players,id',
      'name'        => 'required|string|max:255',
      'surname'     => 'required|string|max:255',
      'email'       => 'nullable|email|max:255',
      'cellNr'      => 'required|string|max:50',
      'dateOfBirth' => 'required|date|before:today',
      'gender'      => 'required|in:Male,Female',
    ]);

    $player = Player::findOrFail($validated['player_id']);

    $player->update([
      'name'        => $validated['name'],
      'surname'     => $validated['surname'],
      'email'       => $validated['email'] ?? $player->email,
      'cellNr'      => $validated['cellNr'],
      'dateOfBirth' => $validated['dateOfBirth'],
      'gender'      => $validated['gender'] === 'Male' ? 1 : 2,
    ]);

    $player->markProfileUpdated();

    return response()->json([
      'success' => true,
      'message' => "Details for \"{$player->name} {$player->surname}\" updated.",
      'player'  => [
        'id'          => $player->id,
        'name'        => $player->name,
        'surname'     => $player->surname,
        'email'       => $player->email,
        'cellNr'      => $player->cellNr,
        'dateOfBirth' => $player->dateOfBirth,
        'gender'      => $player->gender == 1 ? 'Male' : ($player->gender == 2 ? 'Female' : ''),
      ],
    ]);
  }

  public function register(int $id)
  {
    $user = Auth::user();

    // ✅ FIX 1: assign the event properly
    $event = Event::with('eventTypeModel')->findOrFail($id);

    $players = collect(); // Players loaded via AJAX Select2

    $eventCategories = CategoryEvent::where('event_id', $id)
      ->with('category')
      ->get();

    $eventCats = $eventCategories->sortByDesc(
      fn($eventCat) => $eventCat->category->name ?? ''
    );

    /**
     * =========================
     * PAYFAST MODE LOGIC
     * =========================
     */
    $payfast = new Payfast();

    // Admin override
    if ($user->id === 584) {
      $payfast->setMode(config('services.payfast.admin_mode', 0));
    } else {
      $payfast->setMode(1); // live
    }

    /**
     * =========================
     * EVENT FLAGS
     * =========================
     */
    // ✅ FIX 2: use eventTypeModel (not eventType)
    $parentEvent = ($event->eventTypeModel?->id === 9) ? 1 : 0;

    $orderId = 0;

    // Site-wide toggles
    $requireCodeOfConduct = SiteSetting::get('require_code_of_conduct', '0') === '1';
    $requireTerms         = SiteSetting::get('require_terms', '0') === '1';

    // Active Code of Conduct agreement (only load when toggle is on)
    $agreement = $requireCodeOfConduct
      ? Agreement::where('is_active', 1)->latest()->first()
      : null;

    return view('frontend.event.checkout', compact(
      'eventCats',
      'players',
      'eventCategories',
      'event',
      'user',
      'orderId',
      'payfast',
      'parentEvent',
      'agreement',
      'requireCodeOfConduct',
      'requireTerms'
    ));
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
    public function store(Request $request)
    {




        return 'none handled';
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
    //for individual event
  public function notify(Request $request)
  {
    $data = $request->all();

    Log::info('[HYBRID ITN RECEIVED]', $data);

    // 🔐 1️⃣ Validate signature
    if (!$this->validatePayfastSignature()) {


      Log::error('[HYBRID ITN INVALID SIGNATURE]', [
        'data' => $data
      ]);

      return response('Invalid signature', 400);
    }

    // 2️⃣ Only process COMPLETE payments
    if (($data['payment_status'] ?? null) !== 'COMPLETE') {
      return response('Ignored', 200);
    }

    try {

      DB::transaction(function () use ($data) {

        $orderId = (int) ($data['custom_int5'] ?? 0);

        $order = RegistrationOrder::with(['items.category_event.event', 'user.wallet'])
          ->lockForUpdate()
          ->find($orderId);

        if (!$order) {
          throw new \Exception("Order not found");
        }

        // 🔁 Prevent double processing
        if ($order->payfast_paid === true) {
          return;
        }

        // 🔎 Validate amount
        // If payfast_amount_due was reset to 0 (e.g. by cancel), fall back to items total
        $expectedAmount = (float) $order->payfast_amount_due;

        if ($expectedAmount <= 0) {
          $expectedAmount = (float) $order->items->sum('item_price') - (float) $order->wallet_reserved;
          $expectedAmount = round(max($expectedAmount, 0), 2);

          Log::info('[HYBRID ITN] payfast_amount_due was 0, recalculated from items', [
            'order_id' => $order->id,
            'recalculated' => $expectedAmount,
          ]);
        }

        $paidAmount = (float) ($data['amount_gross'] ?? 0);

        if (round($paidAmount, 2) !== round($expectedAmount, 2)) {
          throw new \Exception("Amount mismatch. Expected {$expectedAmount}, got {$paidAmount}");
        }

        // Restore payfast_amount_due so the order record is accurate
        $order->payfast_amount_due = $paidAmount;

        // 3️⃣ Mark PayFast portion
        $order->payfast_paid = true;
        $order->pay_status = 1;
        $order->payfast_pf_payment_id = $data['pf_payment_id'] ?? null;
        $order->save();

        // 4️⃣ Debit wallet if reserved
        if (
          $order->wallet_reserved > 0 &&
          $order->wallet_debited === false
        ) {

          $eventName = optional($order->items->first()?->category_event?->event)->name ?? 'Event Registration';

          app(\App\Services\Wallet\WalletService::class)->debit(
            $order->user->wallet,
            (float) $order->wallet_reserved,
            'event_registration_wallet_payment',
            $order->id,
            [
              'order_id' => $order->id,
              'source' => 'hybrid_notify',
              'reference' => $eventName,
            ]
          );

          activity('wallet')
            ->performedOn($order)
            ->causedBy($order->user)
            ->withProperties([
              'type' => 'debit',
              'amount' => $order->wallet_reserved,
              'reference' => $eventName,
              'order_id' => $order->id,
            ])
            ->log("Wallet debited R{$order->wallet_reserved} for {$eventName}");

          $order->wallet_debited = true;
          $order->save();
        }

        // 5️⃣ Mark registrations as paid
        foreach ($order->items as $item) {

          $registration = Registration::find($item->registration_id);

          if (!$registration) {
            Log::error('[HYBRID ITN] Registration not found for order item', [
              'order_id'         => $order->id,
              'registration_id'  => $item->registration_id,
              'player_id'        => $item->player_id,
              'category_event_id'=> $item->category_event_id,
            ]);
            continue;
          }

          try {
            $registration->players()->syncWithoutDetaching([
              $item->player_id
            ]);
          } catch (\Throwable $e) {
            Log::error('[HYBRID ITN] Failed to sync player to registration', [
              'order_id'        => $order->id,
              'registration_id' => $item->registration_id,
              'player_id'       => $item->player_id,
              'error'           => $e->getMessage(),
            ]);
          }

          try {
            // Check if CER already exists to avoid silent sync failure on unique constraint
            $cerExists = \App\Models\CategoryEventRegistration::where('registration_id', $item->registration_id)
              ->where('category_event_id', $item->category_event_id)
              ->exists();

            if ($cerExists) {
              // Already exists – just update pf_transaction_id if missing
              \App\Models\CategoryEventRegistration::where('registration_id', $item->registration_id)
                ->where('category_event_id', $item->category_event_id)
                ->whereNull('pf_transaction_id')
                ->update([
                  'pf_transaction_id'  => $data['pf_payment_id'] ?? null,
                  'payment_status_id'  => 1,
                  'user_id'            => $order->user_id,
                ]);
            } else {
              $registration->categoryEvents()->syncWithoutDetaching([
                $item->category_event_id => [
                  'payment_status_id' => 1,
                  'user_id'           => $order->user_id,
                  'pf_transaction_id' => $data['pf_payment_id'] ?? null,
                ],
              ]);
            }
          } catch (\Throwable $e) {
            Log::error('[HYBRID ITN] Failed to sync category event registration', [
              'order_id'         => $order->id,
              'registration_id'  => $item->registration_id,
              'category_event_id'=> $item->category_event_id,
              'pf_payment_id'    => $data['pf_payment_id'] ?? null,
              'error'            => $e->getMessage(),
            ]);
          }
        }

        // 6️⃣ Create a Transaction record for this PayFast registration
        try {
          // Enrich PayFast data with order info for transaction creation
          $firstItem = $order->items->first();
          $categoryEvent = $firstItem ? CategoryEvent::with('event', 'category')->find($firstItem->category_event_id) : null;
          $player = $firstItem ? Player::find($firstItem->player_id) : null;
          $event = $categoryEvent?->event;
          $category = $categoryEvent?->category;

          $enrichedData = $data;
          $enrichedData['custom_int5'] = $order->id;
          $enrichedData['custom_int4'] = $order->user_id;
          
          // Add event information
          if ($event) {
            $enrichedData['custom_int3'] = $event->id;
            $enrichedData['custom_str3'] = $event->name;
            $enrichedData['item_name'] = $event->name;
          }
          
          // Add category information
          if ($category) {
            $enrichedData['custom_int1'] = $categoryEvent->id; // category_event_id
            $enrichedData['custom_str1'] = $category->name;
          }
          
          // Add player information
          if ($player) {
            $enrichedData['custom_int2'] = $player->id;
            $enrichedData['custom_str2'] = $player->name . ' ' . $player->surname;
          }

          self::update_transaction($enrichedData, $order);
        } catch (\Throwable $e) {
          Log::error('[HYBRID ITN TRANSACTION FAILED]', [
            'message' => $e->getMessage(),
            'order_id' => $orderId,
          ]);
          // don't rethrow to avoid aborting the rest of the processing here
        }

        Log::info('[HYBRID ITN SUCCESS]', [
          'order_id' => $orderId
        ]);

        // Activity log: registration paid
        activity('registration')
          ->performedOn($order)
          ->causedBy($order->user)
          ->withProperties([
            'order_id' => $order->id,
            'event' => $event?->name ?? optional($order->items->first()?->category_event?->event)->name ?? '',
            'player' => $player ? trim($player->name . ' ' . $player->surname) : '',
            'category' => $category?->name ?? '',
            'method' => 'payfast_hybrid',
            'amount_gross' => $data['amount_gross'] ?? '',
          ])
          ->log('Registration paid via PayFast hybrid');
      });

    } catch (\Throwable $e) {

      Log::error('[HYBRID ITN FAILED]', [
        'message' => $e->getMessage(),
        'order_id' => $data['custom_int5'] ?? null,
        'trace' => $e->getTraceAsString(),
      ]);
    }

    return response('OK', 200)
      ->header('Content-Type', 'text/plain');
  }

  /**
   * Debug route for local development: simulate a PayFast ITN for an order.
   * Marks the order and its registrations as paid.
   */
  public function simulatePayfast($orderId)
  {
    if (!app()->environment('local')) {
      return response('Not allowed', 403);
    }

    $order = RegistrationOrder::with(['items.category_event.event', 'user.wallet'])
      ->lockForUpdate()
      ->find($orderId);

    if (!$order) {
      return response()->json(['error' => 'Order not found'], 404);
    }

    try {
      DB::transaction(function () use ($order) {
        $fakePfId = 'SIM' . time();
        $amount = (float) ($order->payfast_amount_due > 0 ? $order->payfast_amount_due : $order->items->sum('item_price'));

        // Mark order paid
        $order->payfast_amount_due = $amount;
        $order->payfast_paid = true;
        $order->pay_status = 1;
        $order->payfast_pf_payment_id = $fakePfId;
        $order->save();

        // Debit wallet if reserved
        if (
          $order->wallet_reserved > 0 &&
          $order->wallet_debited === false &&
          $order->user &&
          $order->user->wallet
        ) {
          app(\App\Services\Wallet\WalletService::class)->debit(
            $order->user->wallet,
            (float) $order->wallet_reserved,
            'event_registration_wallet_payment_sim',
            $order->id,
            [
              'order_id' => $order->id,
              'source' => 'simulate_payfast',
            ]
          );

          $order->wallet_debited = true;
          $order->save();
        }

        // Mark registrations as paid
        foreach ($order->items as $item) {
          $registration = Registration::find($item->registration_id);
          if (!$registration) {
            continue;
          }

          $registration->players()->syncWithoutDetaching([
            $item->player_id
          ]);

          $registration->categoryEvents()->syncWithoutDetaching([
            $item->category_event_id => [
              'payment_status_id' => 1,
              'user_id' => $order->user_id,
              'pf_transaction_id' => $fakePfId,
            ],
          ]);
        }

        // Create transaction record (best-effort)
        try {
          $fakeData = [
            'custom_int5' => $order->id,
            'custom_int4' => $order->user_id,
            'pf_payment_id' => $fakePfId,
            'amount_gross' => $amount,
          ];
          self::update_transaction($fakeData, $order);
        } catch (\Throwable $e) {
          Log::error('[SIMULATE PAYFAST] transaction failed', ['message' => $e->getMessage()]);
        }
      });

      return response()->json(['success' => true, 'order' => $order->id]);
    } catch (\Throwable $e) {
      Log::error('[SIMULATE PAYFAST] failed', ['message' => $e->getMessage()]);
      return response()->json(['error' => $e->getMessage()], 500);
    }
  }



  public function applyWallet(Request $request)
  {
    $orderId = $request->custom_int5;
    $walletApplied = (float) $request->wallet_applied;

    $wallet = Auth::user()->wallet;

    if (!$wallet || $walletApplied > $wallet->balance) {
      return back()->withErrors('Insufficient wallet balance.');
    }

    try {

      app(\App\Services\Wallet\WalletService::class)->debit(
        $wallet,
        $walletApplied,
        'event_registration_partial_payment',
        $orderId,
        [
          'order_id' => $orderId,
        ]
      );

      return back()->with('success', 'Wallet applied successfully.');

    } catch (\Throwable $e) {
      return back()->withErrors('Wallet application failed.');
    }
  }

  public function cancel(Request $request)
  {
    $walletApplied = $request->custom_wallet_applied ?? 0;
    $orderId = $request->custom_int5 ?? null;

    if ($walletApplied > 0) {

      app(\App\Services\Wallet\WalletService::class)->credit(
        Auth::user()->wallet,
        $walletApplied,
        'event_registration_wallet_reversal',
        $orderId
      );
    }

    return redirect()->route('events.index')
      ->withErrors('Payment cancelled. Wallet funds restored.');
  }

  public function notifyClothing(Request $request)
  {
    // Always respond 200 to PayFast
    // (Laravel will do this automatically when returning a response)

    // Log ITN for debugging (recommended)
    Log::info('PayFast Clothing ITN', $request->all());

    // 1. Only process completed payments
    if ($request->input('payment_status') !== 'COMPLETE') {
      return response('Ignored', 200);
    }

    // 2. Find clothing order
    $orderId = (int) $request->input('custom_int5');
    $order = ClothingOrder::find($orderId);

    if (!$order) {
      Log::error('PayFast ITN: Clothing order not found', [
        'order_id' => $orderId
      ]);
      return response('Order not found', 200);
    }

    // 3. Prevent double processing
    if ((int) $order->pay_status === 1) {
      return response('Already processed', 200);
    }

    // 4. Mark order as paid
    $order->update([
      'pay_status' => 1,
      'pf_id' => $request->input('pf_payment_id'),
      'paid_at' => now(),              // strongly recommended
      'amount_paid' => $request->input('amount_gross'),
    ]);

    return response('OK', 200);
  }

  public function notify_order(Request $request)
    {

        // Tell PayFast that this page is reachable by triggering a header 200
        header('HTTP/1.0 200 OK');
        flush();
        $data = $_POST;
        $order = Order::find($data['custom_int1']);
        $order->pay_status = 1;
        $order->pf_payment_id = $data['pf_payment_id'];
        $order->save();
    }



  public function notify_team(Request $request)
  {
    $data = $request->all();

    Log::info('🟢 TEAM ITN STEP 0: RECEIVED', $data);

    /*
    |--------------------------------------------------------------------------
    | 1️⃣ VERIFY SIGNATURE (INLINE – NO HELPER)
    |--------------------------------------------------------------------------
    */
    $signatureValid = $this->validatePayfastSignature($request);

    Log::info('🟢 TEAM ITN STEP 1: SIGNATURE RESULT', [
      'valid' => $signatureValid
    ]);

    if (!$signatureValid) {
      Log::error('🔴 TEAM ITN FAILED: INVALID SIGNATURE');
      return response('Invalid signature', 400);
    }

    /*
    |--------------------------------------------------------------------------
    | 2️⃣ VALIDATE STATUS
    |--------------------------------------------------------------------------
    */
    Log::info('🟢 TEAM ITN STEP 2: STATUS CHECK', [
      'status' => $data['payment_status'] ?? null
    ]);

    if (($data['payment_status'] ?? '') !== 'COMPLETE') {
      Log::warning('🟡 TEAM ITN IGNORED: NOT COMPLETE');
      return response('Ignored', 200);
    }

    $orderId = (int) ($data['custom_int5'] ?? 0);

    Log::info('🟢 TEAM ITN STEP 3: ORDER ID', [
      'order_id' => $orderId
    ]);

    if (!$orderId) {
      Log::error('🔴 TEAM ITN FAILED: NO ORDER ID');
      return response('No order ID', 400);
    }

    try {

      DB::transaction(function () use ($orderId, $data) {

        Log::info('🟢 TEAM ITN STEP 4: BEGIN TRANSACTION');

        $order = \App\Models\TeamPaymentOrder::lockForUpdate()
          ->with('user.wallet')
          ->find($orderId);

        Log::info('🟢 TEAM ITN STEP 5: ORDER FOUND', [
          'exists' => (bool) $order
        ]);

        if (!$order) {
          throw new \Exception("Team order not found: {$orderId}");
        }

        Log::info('🟢 TEAM ITN STEP 6: ORDER STATE BEFORE UPDATE', [
          'pay_status' => $order->pay_status,
          'payfast_paid' => $order->payfast_paid,
          'wallet_reserved' => $order->wallet_reserved,
          'wallet_debited' => $order->wallet_debited,
          'payfast_amount_due' => $order->payfast_amount_due
        ]);

        /*
        |--------------------------------------------------------------------------
        | IDEMPOTENCY
        |--------------------------------------------------------------------------
        */
        if ((int) $order->pay_status === 1) {
          Log::warning('🟡 TEAM ITN ALREADY PROCESSED');
          return;
        }

        /*
        |--------------------------------------------------------------------------
        | AMOUNT VALIDATION (STRICT)
        |--------------------------------------------------------------------------
        */
        $expected = round((float) $order->payfast_amount_due, 2);
        $received = round((float) ($data['amount_gross'] ?? 0), 2);

        Log::info('🟢 TEAM ITN STEP 7: AMOUNT CHECK', [
          'expected' => $expected,
          'received' => $received
        ]);

        if ($expected !== $received) {
          throw new \Exception("Amount mismatch. Expected {$expected}, got {$received}");
        }

        /*
        |--------------------------------------------------------------------------
        | MARK PAYFAST PAID
        |--------------------------------------------------------------------------
        */
        $order->payfast_paid = true;
        $order->pay_status = 1;
        $order->payfast_pf_payment_id = $data['pf_payment_id'] ?? null;

        $order->save();

        Log::info('🟢 TEAM ITN STEP 8: ORDER UPDATED', [
          'new_pay_status' => $order->pay_status
        ]);

        /*
        |--------------------------------------------------------------------------
        | WALLET DEBIT (HYBRID)
        |--------------------------------------------------------------------------
        */
        if (
          $order->wallet_reserved > 0 &&
          !$order->wallet_debited &&
          $order->user &&
          $order->user->wallet
        ) {

          Log::info('🟢 TEAM ITN STEP 9: DEBIT WALLET', [
            'amount' => $order->wallet_reserved
          ]);

          $eventName = optional($order->event)->name ?? 'Team Registration';

          app(\App\Services\Wallet\WalletService::class)->debit(
            $order->user->wallet,
            (float) $order->wallet_reserved,
            'team_registration_wallet_payment',
            $order->id,
            [
              'order_id' => $order->id,
              'source' => 'team_hybrid_notify',
              'reference' => $eventName,
            ]
          );

          activity('wallet')
            ->performedOn($order)
            ->causedBy($order->user)
            ->withProperties([
              'type' => 'debit',
              'amount' => $order->wallet_reserved,
              'reference' => $eventName,
              'order_id' => $order->id,
            ])
            ->log("Wallet debited R{$order->wallet_reserved} for {$eventName}");

          $order->wallet_debited = true;
          $order->save();

          Log::info('🟢 TEAM ITN STEP 10: WALLET DEBITED');
        } else {
          Log::info('🟢 TEAM ITN STEP 9: NO WALLET DEBIT NEEDED');
        }

        /*
        |--------------------------------------------------------------------------
        | TEAM PLAYER UPDATE
        |--------------------------------------------------------------------------
        */
        $teamPlayer = \App\Models\TeamPlayer::where('team_id', $order->team_id)
          ->where('player_id', $order->player_id)
          ->first();

        Log::info('🟢 TEAM ITN STEP 11: TEAM PLAYER FOUND', [
          'exists' => (bool) $teamPlayer
        ]);

        if ($teamPlayer) {

          Log::info('🟢 TEAM ITN STEP 12: TEAM PLAYER BEFORE', [
            'pay_status' => $teamPlayer->pay_status
          ]);

          $teamPlayer->pay_status = 1;
          $teamPlayer->save();

          Log::info('🟢 TEAM ITN STEP 13: TEAM PLAYER UPDATED', [
            'new_pay_status' => $teamPlayer->pay_status
          ]);
        }

        Log::info('🟢 TEAM ITN STEP 14: SUCCESS');

        $teamEventName = optional($order->event)->name ?? 'Team Event';
        $teamPlayerObj = \App\Models\Player::find($order->player_id);

        activity('registration')
          ->performedOn($order)
          ->causedBy($order->user)
          ->withProperties([
            'order_id' => $order->id,
            'event' => $teamEventName,
            'player' => $teamPlayerObj ? trim($teamPlayerObj->name . ' ' . $teamPlayerObj->surname) : '',
            'team_id' => $order->team_id,
            'method' => 'payfast_team',
            'amount' => $data['amount_gross'] ?? '',
          ])
          ->log("Team registration paid for {$teamEventName}");
      });

    } catch (\Throwable $e) {

      Log::error('🔴 TEAM ITN FAILED', [
        'order_id' => $orderId,
        'message' => $e->getMessage()
      ]);

      return response('Error', 500);
    }

    return response('OK', 200)
      ->header('Content-Type', 'text/plain');
  }


  private function calculateTeamAmount($teamId)
  {
    $team = Team::with('regions', 'event')->find($teamId);

    if (!$team) {
      return 0;
    }

    $eventFee = (float) ($team->event->entryFee ?? 0);

    $regionFee = (
      $team->regions &&
      (float) $team->regions->region_fee > 0
    )
      ? (float) $team->regions->region_fee
      : 0;

    return $eventFee + $regionFee;
  }

  public function updateTeamPayment($data)
  {
    $teamId = $data['custom_int1'] ?? null;
    $playerId = $data['custom_int2'] ?? null;

    if (!$teamId || !$playerId) {
      return null;
    }

    $team = Team::find($teamId);
    $player = Player::find($playerId);

    if (!$team || !$player) {
      return null;
    }

    $teamPlayer = TeamPlayer::where('team_id', $team->id)
      ->where('player_id', $player->id)
      ->first();

    if (!$teamPlayer) {
      return null;
    }

    // ✅ Mark paid
    $teamPlayer->pay_status = 1;
    $teamPlayer->save();

    return $teamPlayer;
  }


  public function updateRegistrationFromPayfast($payfastData)
  {
    $registrationOrder = RegistrationOrder::find($payfastData['custom_int5']);

    if (!$registrationOrder) {
      throw new \Exception("Registration order not found for ID: {$payfastData['custom_int5']}");
    }

    foreach ($registrationOrder->items as $item) {
      $registration = Registration::find($item->registration_id);
      if (!$registration) {
        continue; // skip if missing
      }

      // Attach player (avoid duplicates)
      $registration->players()->syncWithoutDetaching([$item->player_id]);

      // Attach category event with pivot data (avoid duplicates)
      $registration->categoryEvents()->syncWithoutDetaching([
        $item->category_event_id => [
          'payment_status_id' => 1,
          'user_id' => $payfastData['custom_int4'],
          'pf_transaction_id' => $payfastData['pf_payment_id'],
        ],
      ]);
    }

    return $this::update_transaction($payfastData, $registrationOrder);
  }


  public function registerPlayerInCategoryFromAdmin(Request $request)
    {

        $registration = new Registration();
        $registration->save();
        $registration->players()->attach($request->player_id);
        $order = 'admin';
        $trans = RegisterController::update_transaction($request, $order);
        $registration->categoryEvents()->attach($request->categoryEvent, [
            'payfast_id' =>  'Admin',
            'payment_status_id' => 0,
            'user_id' => Auth::user()->id,
            'pf_transaction_id' => $trans->id,
        ]);

        return 'success';
    }
    public function payfast(Request $request)
    {



        return $request;
    }

  public static function update_transaction(array $data, $order)
  {
    /**
     * =====================================================
     * ADMIN REGISTRATION / REMOVE
     * =====================================================
     */
    if ($order === 'admin') {

      $categoryEvent = CategoryEvent::with('event', 'category')->find($data['categoryEvent'] ?? null);
      if (!$categoryEvent) {
        return null;
      }

      $transaction = new Transaction();
      $transaction->transaction_type = 'Registration';
      $transaction->amount_gross = 0;
      $transaction->amount_net = 0;
      $transaction->amount_fee = 0;
      $transaction->event_id = $categoryEvent->event->id;
      $transaction->item_name = $categoryEvent->event->name;
      $transaction->category_event_id = $categoryEvent->id;
      $transaction->player_id = $data['player_id'] ?? null;

      $transaction->custom_str1 = 'Admin Remove';

      // Add category name
      if ($categoryEvent->category) {
        $transaction->custom_str1 = $categoryEvent->category->name;
      }

      if (!empty($data['player_id'])) {
        $player = Player::find($data['player_id']);
        if ($player) {
          $transaction->custom_int2 = $player->id;
          $transaction->custom_str2 = $player->name . ' ' . $player->surname;
        }
      }

      $transaction->custom_int3 = $categoryEvent->event->id;
      $transaction->custom_str3 = $categoryEvent->event->name;

      // Auth only if available (NOT during ITN)
      if (auth()->check()) {
        $transaction->custom_int4 = auth()->id();
        $transaction->custom_str4 = auth()->user()->name;
      }

      $transaction->save();
      return $transaction;
    }

    /**
     * =====================================================
     * WITHDRAWAL BEFORE DEADLINE
     * =====================================================
     */
    if ($order === 'withdrawel_before_deadline') {

      $registration = CategoryEventRegistration::with('categoryEvent.event', 'categoryEvent.category')
        ->find($data['categoryEventRegistration'] ?? null);

      if (!$registration) {
        return null;
      }

      $transaction = new Transaction();
      $transaction->transaction_type = 'Withdrawal';
      $transaction->category_event_id = $registration->category_event_id;
      $transaction->event_id = $registration->categoryEvent->event->id;
      $transaction->item_name = $registration->categoryEvent->event->name;

      // Add category information
      if ($registration->categoryEvent->category) {
        $transaction->custom_int1 = $registration->category_event_id;
        $transaction->custom_str1 = $registration->categoryEvent->category->name;
      }

      if ($registration->payfast_id === 'Admin') {
        $transaction->amount_gross = 0;
        $transaction->amount_fee = 0;
        $transaction->amount_net = 10;
      } else {
        $entryFee = $registration->categoryEvent->entry_fee;

        $payfastFee = \App\Models\SiteSetting::calculatePayfastFee($entryFee);

        $transaction->cape_tennis_fee = 10;
        $transaction->amount_gross = -$entryFee;
        $transaction->amount_fee = -$payfastFee;
        $transaction->amount_net = ($entryFee - ($payfastFee - 10));
      }

      $player = $registration->registration->players->first();
      if ($player) {
        $transaction->custom_int2 = $player->player_id;
        $transaction->custom_str2 = $player->name . ' ' . $player->surname;
        $transaction->player_id = $player->player_id;
      }

      $transaction->custom_int3 = $registration->categoryEvent->event->id;
      $transaction->custom_str3 = $registration->categoryEvent->event->name;

      if (auth()->check()) {
        $transaction->custom_int4 = auth()->id();
        $transaction->custom_str4 = auth()->user()->name;
      }

      $transaction->save();
      return $transaction;
    }

    /**
     * =====================================================
     * PAYFAST REGISTRATION (STANDARD ITN)
     * =====================================================
     */

    $transaction = new Transaction();
    $transaction->transaction_type = 'Registration';

    $transaction->amount_gross = $data['amount_gross'] ?? null;

    // Use configured PayFast fee percentage per payment method (benefits Cape Tennis on negotiated discount)
    $gross = (float) ($data['amount_gross'] ?? 0);
    $paymentMethod = $data['payment_method'] ?? null;
    $configuredFee = \App\Models\SiteSetting::calculatePayfastFee($gross, $paymentMethod);
    $transaction->amount_fee = $configuredFee;
    $transaction->amount_net = round($gross - $configuredFee, 2);

    $transaction->event_id = $data['custom_int3'] ?? null;
    $transaction->category_event_id = $data['custom_int1'] ?? null;
    $transaction->player_id = $data['custom_int2'] ?? null;

    foreach (['1', '2', '3', '4', '5'] as $i) {
      $intKey = "custom_int{$i}";
      $strKey = "custom_str{$i}";

      if (!empty($data[$intKey])) {
        $transaction->{$intKey} = $data[$intKey];
      }

      if (!empty($data[$strKey])) {
        $transaction->{$strKey} = $data[$strKey];
      }
    }

    if (!empty($data['pf_payment_id'])) {
      $transaction->pf_payment_id = $data['pf_payment_id'];
    }

    $transaction->item_name = $data['item_name'] ?? null;
    $transaction->email_address = $data['email_address'] ?? null;

    $transaction->save();
    return $transaction;
  }

  public function payNowPayfast(Request $request)
  {

    // ----------------------------
    // Validate terms accepted
    // ----------------------------
    if (!$request->has('terms_accepted') || $request->terms_accepted != '1') {
      return back()->withErrors([
        'msg' => 'You must accept the terms and conditions and Code of Conduct before proceeding.'
      ]);
    }

    // ----------------------------
    // Validate players/categories
    // ----------------------------
    foreach ($request->player as $player) {
      if ($player == 0) {
        return back()->withErrors([
          'msg' => 'Please confirm that you have selected a player and category for each player!'
        ]);
      }
    }

    foreach ($request->category as $cat) {
      if ($cat == 0) {
        return back()->withErrors([
          'msg' => 'Please confirm that you have selected a player and category for each player!'
        ]);
      }
    }
 
    // ----------------------------
    // Create order
    // ----------------------------
    $regorder = new RegistrationOrder();
    $regorder->user_id = Auth::id();
    $regorder->save();

    $totalFee = 0;

    // Create items
    for ($i = 0; $i < count($request->player); $i++) {

      $categoryEvent = CategoryEvent::findOrFail($request->category[$i]);

      $registration = new Registration();
      $registration->save();

      $order = new RegistrationOrderItems();
      $order->order_id = $regorder->id;
      $order->category_event_id = $categoryEvent->id;
      $order->registration_id = $registration->id;
      $order->player_id = $request->player[$i];
      $order->user_id = Auth::id();
      $order->item_price = $categoryEvent->entry_fee ?? 0;
      $order->save();

      // Attach player to registration + create category_event_registration immediately
      $registration->players()->syncWithoutDetaching([$request->player[$i]]);
      $registration->categoryEvents()->syncWithoutDetaching([
        $categoryEvent->id => [
          'payment_status_id' => 0,
          'user_id' => Auth::id(),
        ],
      ]);

      $totalFee += $order->item_price;
    }

    if ($totalFee <= 0) {
      // Free event — mark as paid immediately
      foreach ($regorder->items as $item) {
        $reg = Registration::find($item->registration_id);
        if ($reg) {
          $reg->categoryEvents()->updateExistingPivot($item->category_event_id, [
            'payment_status_id' => 1,
          ]);
        }
      }
      $regorder->pay_status = 1;
      $regorder->payfast_paid = true;
      $regorder->wallet_debited = true;
      $regorder->save();

      return redirect()
        ->route('frontend.registration.success', ['order' => $regorder->id])
        ->with('success', 'Your registration was successful (no payment required).');
    }

    // -----------------------------------
    // 🔵 WALLET – DO NOT AUTO-APPLY
    // Let the user choose on checkout page
    // -----------------------------------

    $wallet = Auth::user()->wallet;
    $walletBalance = $wallet?->balance ?? 0;

    $regorder->wallet_reserved = 0;
    $regorder->payfast_amount_due = $totalFee;
    $regorder->wallet_debited = false;
    $regorder->payfast_paid = false;
    $regorder->save();

    // -----------------------------------
    // 🔴 PayFast for full amount
    // -----------------------------------

    $payfast = new Payfast();
    $payfast->setMode(Auth::id() == 584 ? 0 : 1);

    $request['amount'] = $totalFee;
    $request['custom_int5'] = $regorder->id;
    $request['custom_wallet_reserved'] = 0;
    Log::info('HYBRID CREATE ORDER', [
      'order_id' => $regorder->id,
      'total_fee' => $totalFee,
      'wallet_balance' => $walletBalance,
      'wallet_reserved' => 0,
      'payfast_due' => $totalFee
    ]);

    return view('frontend.payfast.check_out', compact('request', 'payfast'));
  }



  public function registrationSuccess($orderId)
  {
    $order = RegistrationOrder::with('items.category_event')->findOrFail($orderId);

    // Redirect back to the event the user registered for
    $eventId = optional($order->items->first()?->category_event)->event_id;

    if ($eventId) {
      return redirect()->route('events.show', $eventId)
        ->with('success', 'Registration completed successfully!');
    }

    return view('frontend.event.registration_success', compact('order'));
  }

  public function payOrderPayfast(Request $request)
    {
        $order = new Order();
        $order->user_id = Auth::user()->id;
        $order->save();

        for ($i = 0; $i < count($request->product); $i++) {
            $p = SellProduct::find($request->product[$i]);
            $orderItem = new OrderItem();
            $orderItem->order_id = $order->id;
            $orderItem->product_id = $p->id;
            $orderItem->nrOf = $request->nrOf[$i];
            $orderItem->user_id = Auth::user()->id;
            $orderItem->name = $request->name;
            $orderItem->save();
        }

        $mode = 'live';
        $payfast = new Payfast();
        //0 for sandbox and 1 for live
        if (Auth::user()->id == 584) {
            if ($mode == 'test') {
                $payfast->setMode(2);
            } else {
                $payfast->setMode(0);
            }
        } else {
            $payfast->setMode(1);
        }
        $amount = 0;
        for ($i = 0; $i < count($request->product); $i++) {

            $product = SellProduct::find($request->product[$i])->price;
            $nrOf = $request->nrOf[$i];
            $amount += $product * $nrOf;
        }



        $request['notify_url'] = $payfast->notify_url . '_order';
        $request['cancel_url'] = $payfast->cancel_url;

        $request['amount'] = $amount;
        $request['item_name'] = 'Meals';
        $request['custom_int1'] = $order->id;
        $request['custom_str1'] = 'ordernr';

        return view('frontend.payfast.pay_now', compact('request', 'payfast'));
    }

}
