<?php

namespace App\Http\Controllers\Backend;

use App\Services\TransactionHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Frontend\RegisterController;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Registration;
use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class RegistrationController extends Controller
{
  protected $transaction;
  public function __construct(TransactionHelper $transaction)
  {

    $this->transaction = $transaction;
  }
  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index()
  {
    //
  }

  /**
   * Show the form for creating a new resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function create()
  {
    //
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request)
  {
    //
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
    $registration = Registration::find($id);
    return '$registration';
  }


  public function delete(Request $request)
  {
    $registration_id = (int) $request->id;
    $categoryEventId = (int) $request->categoryEvent;

    return DB::transaction(function () use ($registration_id, $categoryEventId) {

      // 🔒 Find registration entry (must exist)
      $categoryEventRegistration = CategoryEventRegistration::where(
        'category_event_id',
        $categoryEventId
      )->where(
          'registration_id',
          $registration_id
        )->first();

      if (!$categoryEventRegistration) {
        return response()->json([
          'status' => 'error',
          'message' => 'Registration not found for this category event',
        ], 404);
      }

      // 🔒 Prevent double withdrawal
      if ($categoryEventRegistration->withdrawn_at ?? false) {
        return response()->json([
          'status' => 'error',
          'message' => 'Registration already withdrawn',
        ], 409);
      }

      // 🔎 Correct order item lookup
      $orderItem = RegistrationOrderItems::where(
        'registration_id',
        $registration_id
      )->where(
          'category_event_id',
          $categoryEventId
        )->first();

      $user_id = $orderItem?->user_id ?? 0;

      /**
       * 💰 Financial reversal
       */
      $response = $this->transaction->withdrawal($categoryEventRegistration);

      /**
       * 🧹 Domain cleanup
       */
      $this->withdraw($registration_id, $categoryEventId);

      /**
       * 🧾 Soft mark as withdrawn (preferred)
       */
      $categoryEventRegistration->update([
        'withdrawn_at' => now(),
      ]);

      return response()->json([
        'status' => 'success',
        'data' => $response,
      ]);
    });
  }

  public function withdraw($registration_id, $categoryEvent_id)
  {
    $withdrawal = new Withdrawals();
    $withdrawal->user_id = Auth::user()->id;
    $withdrawal->registration_id = $registration_id;
    $withdrawal->category_event_id = $categoryEvent_id;
    $withdrawal->save();

    return $withdrawal;
  }

  public function withdraw_player(Request $request)
  {
    $categoryEventRegistration = CategoryEventRegistration::find($request->categoryEventRegistration);

    $this->withdraw($categoryEventRegistration->registration_id, $categoryEventRegistration->category_event_id);

    $user = Auth::user();
    $user = User::find($user->id);

    $user->deposit(($categoryEventRegistration->categoryEvent->entry_fee) - 10);
    $order = 'withdrawel_before_deadline';
    $trans = RegisterController::update_transaction($request, $order);
    CategoryEventRegistration::where('id', $request->categoryEventRegistration)->delete();

    // have to send mail to admin and owner

    //refund payfast or wallet?


    return $trans;
  }


  public function addPlayerToCategory(Request $request)
  {
    \Log::info('[ADD PLAYER TO CATEGORY PAYLOAD]', $request->all());

    $validated = $request->validate([
      'player_id' => 'required|integer|exists:players,id',
      'category_event_id' => 'required|integer|exists:category_events,id',
      'event_id' => 'required|integer|exists:events,id',
    ]);

    $playerId = $validated['player_id'];
    $categoryEventId = $validated['category_event_id'];
    $eventId = $validated['event_id'];

    // ✅ Step 1: Check if this player already exists for this category event
    $exists = \App\Models\CategoryEventRegistration::where('category_event_id', $categoryEventId)
      ->whereHas('registration.players', function ($q) use ($playerId) {
        $q->where('players.id', $playerId);
      })
      ->exists();

    if ($exists) {
      return response()->json([
        'success' => false,
        'message' => 'Player is already registered in this category.',
      ]);
    }

    // ✅ Step 2: Create a new registration
    $registration = \App\Models\Registration::create();

    // ✅ Step 3: Attach player to registration
    $registration->players()->attach($playerId);

    // ✅ Step 4: Attach registration to category event
    $registration->categoryEvents()->attach($categoryEventId, [
      'user_id' => $playerId,
      'payment_status_id' => 1,
    ]);

    // ✅ Step 5: Fetch player info (email + cell)
    $player = \App\Models\Player::find($playerId);

    // ✅ Step 6: Return success JSON with contact info
    return response()->json([
      'success' => true,
      'message' => 'Player added successfully!',
      'registration_id' => $registration->id,
      'email' => $player->email ?? null,
      'cellNr' => $player->cellNr ?? null,
    ]);
  }

}

