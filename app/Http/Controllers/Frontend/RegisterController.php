<?php

namespace App\Http\Controllers\Frontend;

use App\Classes\Payfast;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\ClothingOrder;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
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
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function create()
    {
        dd('index');
    }


  public function register(int $id)
  {
    $user = Auth::user();

    // ✅ FIX 1: assign the event properly
    $event = Event::with('eventTypeModel')->findOrFail($id);

    $players = Player::all();

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

    return view('frontend.event.checkout', compact(
      'eventCats',
      'players',
      'eventCategories',
      'event',
      'user',
      'orderId',
      'payfast',
      'parentEvent'
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

        $order = RegistrationOrder::with(['items', 'user.wallet'])
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
        $expectedAmount = (float) $order->payfast_amount_due;

        $paidAmount = (float) ($data['amount_gross'] ?? 0);

        if (round($paidAmount, 2) !== round($expectedAmount, 2)) {
          throw new \Exception("Amount mismatch. Expected {$expectedAmount}, got {$paidAmount}");
        }

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

          app(\App\Services\Wallet\WalletService::class)->debit(
            $order->user->wallet,
            (float) $order->wallet_reserved,
            'event_registration_wallet_payment',
            $order->id,
            [
              'order_id' => $order->id,
              'source' => 'hybrid_notify',
            ]
          );

          $order->wallet_debited = true;
          $order->save();
        }

        // 5️⃣ Mark registrations as paid
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
              'pf_transaction_id' => $data['pf_payment_id'] ?? null,
            ],
          ]);
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

          app(\App\Services\Wallet\WalletService::class)->debit(
            $order->user->wallet,
            (float) $order->wallet_reserved,
            'team_registration_wallet_payment',
            $order->id,
            [
              'order_id' => $order->id,
              'source' => 'team_hybrid_notify'
            ]
          );

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

        $payfastFee = ((($entryFee * 3.2) / 100) + 2) * 1.14;

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
    $transaction->amount_net = $data['amount_net'] ?? null;
    $transaction->amount_fee = $data['amount_fee'] ?? null;

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

      $totalFee += $order->item_price;
    }

    if ($totalFee <= 0) {
      return redirect()
        ->route('frontend.registration.success', ['order' => $regorder->id])
        ->with('success', 'Your registration was successful (no payment required).');
    }

    // -----------------------------------
    // 🔵 SAFE HYBRID LOGIC (RESERVE ONLY)
    // -----------------------------------

    $wallet = Auth::user()->wallet;
    $walletBalance = $wallet?->balance ?? 0;

    $walletApplied = min($walletBalance, $totalFee);
    $remaining = round($totalFee - $walletApplied, 2);

    // 🔐 DO NOT DEBIT HERE
    // Just reserve

    $regorder->wallet_reserved = $walletApplied;
    $regorder->payfast_amount_due = $remaining;
    $regorder->wallet_debited = false;
    $regorder->payfast_paid = false;
    $regorder->save();

    // If wallet covers everything
    if ($remaining <= 0) {

      return redirect()
        ->route('registration.hybrid.complete', $regorder->id);
    }

    // -----------------------------------
    // 🔴 PayFast for remaining
    // -----------------------------------

    $payfast = new Payfast();
    $payfast->setMode(Auth::id() == 584 ? 0 : 1);

    $request['amount'] = $remaining;
    $request['custom_int5'] = $regorder->id;
    $request['custom_wallet_reserved'] = $walletApplied;
    Log::info('HYBRID CREATE ORDER', [
      'order_id' => $regorder->id,
      'total_fee' => $totalFee,
      'wallet_reserved' => $walletApplied,
      'payfast_due' => $remaining
    ]);

    return view('frontend.payfast.check_out', compact('request', 'payfast'));
  }



  public function registrationSuccess($orderId)
  {
    $order = RegistrationOrder::with('items')->findOrFail($orderId);
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

  /**
   * PayFast ITN signature validation (deep debug)
   *
   * How to use:
   * 1) Deploy this method
   * 2) Trigger one PayFast ITN
   * 3) Inspect logs for:
   *    - [PF_SIG] RAW
   *    - [PF_SIG] RECEIVED_SIG
   *    - [PF_SIG] STRING_NO_SIG
   *    - [PF_SIG] STRING_WITH_PASSPHRASE
   *    - [PF_SIG] GENERATED_SIG
   *    - [PF_SIG] MATCH
   *    - [PF_SIG] FIELD_DUMP (order + values)
   */
  private function validatePayfastSignature(): bool
  {
    $rawPost = file_get_contents('php://input');

    Log::info('[PF_SIG][1] RAW', [
      'len' => is_string($rawPost) ? strlen($rawPost) : 0,
      'raw' => $rawPost,
    ]);

    if (!is_string($rawPost) || trim($rawPost) === '') {
      Log::warning('[PF_SIG] EMPTY_RAW');
      return false;
    }

    // Parse raw into array (this is ONLY for extracting values, not for rebuilding signature string)
    $parsed = [];
    parse_str($rawPost, $parsed);

    Log::info('[PF_SIG][2] PARSED_KEYS', [
      'keys' => array_keys($parsed),
      'merchant_id' => $parsed['merchant_id'] ?? null,
      'pf_payment_id' => $parsed['pf_payment_id'] ?? null,
      'payment_status' => $parsed['payment_status'] ?? null,
    ]);

    $receivedSignature = $parsed['signature'] ?? null;

    Log::info('[PF_SIG][3] RECEIVED_SIG', [
      'received' => $receivedSignature,
    ]);

    if (!$receivedSignature) {
      Log::warning('[PF_SIG] MISSING_SIGNATURE');
      return false;
    }

    // IMPORTANT:
    // Build the signature base string from RAW POST EXACTLY, only removing "&signature=..."
    // (PayFast signature depends on original order + original encoding)
    $needle1 = '&signature=' . $receivedSignature;
    $needle2 = 'signature=' . $receivedSignature . '&';
    $needle3 = 'signature=' . $receivedSignature;

    $stringNoSig = $rawPost;

    if (str_contains($stringNoSig, $needle1)) {
      $stringNoSig = str_replace($needle1, '', $stringNoSig);
      Log::info('[PF_SIG][4] REMOVED_SIG_STYLE', ['style' => 'ampersand_prefix']);
    } elseif (str_contains($stringNoSig, $needle2)) {
      $stringNoSig = str_replace($needle2, '', $stringNoSig);
      Log::info('[PF_SIG][4] REMOVED_SIG_STYLE', ['style' => 'middle_param']);
    } elseif (str_contains($stringNoSig, $needle3)) {
      $stringNoSig = str_replace($needle3, '', $stringNoSig);
      $stringNoSig = rtrim($stringNoSig, '&');
      Log::info('[PF_SIG][4] REMOVED_SIG_STYLE', ['style' => 'end_param_or_no_amp']);
    } else {
      Log::warning('[PF_SIG] SIGNATURE_SUBSTRING_NOT_FOUND_IN_RAW', [
        'expected_needles' => [$needle1, $needle2, $needle3],
      ]);
      // Continue anyway, but this usually means the raw payload differs from what we parsed
    }

    // Remove any trailing & caused by replacement
    $stringNoSig = rtrim($stringNoSig, '&');

    Log::info('[PF_SIG][5] STRING_NO_SIG', [
      'string' => $stringNoSig,
    ]);

    // Decide passphrase based on merchant_id
    $merchantId = $parsed['merchant_id'] ?? null;

    $sandboxMerchant = (string) env('PAYFAST_MERCHANT_ID_SANDBOX');
    $liveMerchant = (string) env('PAYFAST_MERCHANT_ID_LIVE');

    $passphrase = null;
    if ($merchantId !== null && (string) $merchantId === $sandboxMerchant) {
      $passphrase = env('PAYFAST_PASSPHRASE_SANDBOX');
      Log::info('[PF_SIG][6] MODE', ['mode' => 'sandbox', 'merchant_id' => $merchantId]);
    } else {
      $passphrase = env('PAYFAST_PASSPHRASE_LIVE');
      Log::info('[PF_SIG][6] MODE', ['mode' => 'live', 'merchant_id' => $merchantId]);
    }

    Log::info('[PF_SIG][7] PASSPHRASE_PRESENT', [
      'has_passphrase' => !empty($passphrase),
      // do not log actual passphrase in production
    ]);

    $stringWithPassphrase = $stringNoSig;
    if (!empty($passphrase)) {
      $stringWithPassphrase .= '&passphrase=' . urlencode($passphrase);
    }

    Log::info('[PF_SIG][8] STRING_WITH_PASSPHRASE', [
      'string' => $stringWithPassphrase,
    ]);

    $generatedSignature = md5($stringWithPassphrase);

    Log::info('[PF_SIG][9] GENERATED_SIG', [
      'generated' => $generatedSignature,
      'received' => $receivedSignature,
    ]);

    $match = hash_equals($generatedSignature, $receivedSignature);

    Log::info('[PF_SIG][10] MATCH', [
      'match' => $match,
    ]);

    // Extra: dump the parsed fields in raw-order appearance (best-effort)
    // This helps spot encoding/order problems quickly.
    $fieldDump = [];
    $parts = explode('&', $rawPost);
    foreach ($parts as $p) {
      if ($p === '')
        continue;
      [$k, $v] = array_pad(explode('=', $p, 2), 2, '');
      $fieldDump[] = [
        'k' => $k,
        'v' => $v,
      ];
    }

    Log::info('[PF_SIG][11] FIELD_DUMP_RAW_ORDER', [
      'fields' => $fieldDump,
    ]);

    return $match;
  }


}
