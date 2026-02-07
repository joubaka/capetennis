<?php

namespace App\Http\Controllers\backend;

use App\Classes\Payfast;
use App\Exports\ClothingOrdersExport;
use App\Http\Controllers\Controller;
use App\Models\ClothingItemType;
use App\Models\ClothingOrder;
use App\Models\ClothingOrderItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TeamRegion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;


use Illuminate\Support\Facades\DB;

class ClothingOrderController extends Controller
{
  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index()
  {
    $data['clothings'] = ClothingOrder::all();

    return view('backend.clothing.clothing-index', $data);
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



  public function store(Request $request)
  {
    // ==============================
    // ENTRY POINT
    // ==============================
    logger()->info('[ClothingOrder] store() entered');

    logger()->info('[ClothingOrder] raw request payload', $request->all());

    $userId = Auth::id();

    logger()->info('[ClothingOrder] user id', ['user_id' => $userId]);

    if (!$userId) {
      logger()->warning('[ClothingOrder] not authenticated');
      return redirect()
        ->route('login')
        ->with('error', 'Please log in to place an order.');
    }

    // ==============================
    // VALIDATION
    // ==============================
    try {
      $validated = $request->validate([
        'player_id' => ['nullable', 'integer', 'exists:players,id'],
        'team_id' => ['nullable', 'integer', 'exists:teams,id'],
        'event_id' => ['required', 'integer', 'exists:events,id'],

        'items' => ['required', 'array'],
        'items.*.size' => ['required', 'integer'],
        'items.*.qty' => ['required', 'integer', 'min:1'],
      ], [
        'items.required' => 'Please select at least one clothing item.',
      ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
      logger()->error('[ClothingOrder] validation failed', [
        'errors' => $e->errors(),
        'payload' => $request->all(),
      ]);
      throw $e;
    }

    logger()->info('[ClothingOrder] validation passed', $validated);

    // ==============================
    // NORMALISE ITEMS
    // ==============================
    $pairs = [];

    foreach ($validated['items'] as $itemTypeId => $row) {
      $itemTypeId = (int) $itemTypeId;
      $sizeId = (int) $row['size'];
      $qty = (int) $row['qty'];

      logger()->info('[ClothingOrder] processing item row', [
        'item_id' => $itemTypeId,
        'size_id' => $sizeId,
        'qty' => $qty,
      ]);

      if ($itemTypeId > 0 && $sizeId > 0 && $qty > 0) {
        $pairs[] = [
          'item_id' => $itemTypeId,
          'size_id' => $sizeId,
          'qty' => $qty,
        ];
      }
    }

    logger()->info('[ClothingOrder] normalised pairs', $pairs);

    if (empty($pairs)) {
      logger()->warning('[ClothingOrder] no valid item pairs built');
      return back()
        ->withInput()
        ->with('error', 'Please select at least one item with a size.');
    }

    // ==============================
    // PRELOAD PRICES
    // ==============================
    $itemIds = collect($pairs)->pluck('item_id')->unique();

    logger()->info('[ClothingOrder] loading prices for item ids', [
      'item_ids' => $itemIds->values()->all(),
    ]);

    $pricesByItem = ClothingItemType::whereIn('id', $itemIds)
      ->pluck('price', 'id');

    logger()->info('[ClothingOrder] prices loaded', $pricesByItem->toArray());

    // ==============================
    // DATABASE TRANSACTION
    // ==============================
    try {
      DB::beginTransaction();
      logger()->info('[ClothingOrder] transaction started');

      $order = new ClothingOrder();
      $order->player_id = $validated['player_id'] ?? null;
      $order->team_id = $validated['team_id'] ?? null;
      $order->event_id = $validated['event_id'];
      $order->user_id = $userId;
      $order->pay_status = 0;
      $order->total = 0;
      $order->save();

      logger()->info('[ClothingOrder] order created', [
        'order_id' => $order->id,
      ]);

      $total = 0;
      $rows = [];

      foreach ($pairs as $p) {
        $price = (float) ($pricesByItem[$p['item_id']] ?? 0);
        $lineTotal = $price * $p['qty'];
        $total += $lineTotal;

        logger()->info('[ClothingOrder] order line', [
          'item_id' => $p['item_id'],
          'size_id' => $p['size_id'],
          'qty' => $p['qty'],
          'price' => $price,
          'line_total' => $lineTotal,
        ]);

        $rows[] = [
          'clothing_order_id' => $order->id,
          'clothing_order_item_id' => $p['item_id'],
          'clothing_item_size' => $p['size_id'],
          'qty' => $p['qty'],
          'price' => $price,
          'line_total' => $lineTotal,
          'created_at' => now(),
          'updated_at' => now(),
        ];
      }

      if ($rows) {
        ClothingOrderItem::insert($rows);
        logger()->info('[ClothingOrder] order items inserted', [
          'count' => count($rows),
        ]);
      }

      $order->update(['total' => $total]);

      logger()->info('[ClothingOrder] order total updated', [
        'total' => $total,
      ]);

      DB::commit();
      logger()->info('[ClothingOrder] transaction committed');

    } catch (\Throwable $e) {
      DB::rollBack();

      logger()->error('[ClothingOrder] transaction failed', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);

      return back()
        ->withInput()
        ->with('error', 'Could not create clothing order. Please try again.');
    }

    // ==============================
    // PAYFAST SETUP
    // ==============================
    logger()->info('[ClothingOrder] preparing PayFast redirect');

    // load items for the view
    $orderItems = ClothingOrderItem::where('clothing_order_id', $order->id)->get();

    // PayFast
    $payfast = new \App\Classes\Payfast();

    // MODE FIRST
    if ($userId === 584) {
      $payfast->setMode(0); // sandbox
    }

    // URLs
    $payfast->setReturnUrl('/events/' . $validated['event_id']);
    

    // REQUIRED FIELDS (USE SETTERS)
    $payfast->setItem('Clothing Order #' . $order->id);
    $payfast->setAmount((float) $total);

    // Tracking
    $payfast->custom_int1 = (int) ($validated['team_id'] ?? 0);
    $payfast->custom_int2 = (int) ($validated['player_id'] ?? 0);
    $payfast->custom_int3 = (int) $validated['event_id'];
    $payfast->custom_int4 = (int) $userId;
    $payfast->custom_int5 = (int) $order->id;
    $payfast->custom_str1 = 'Team';
    $payfast->custom_str2 = 'Player';
    $payfast->custom_str3 = 'Event';
    $payfast->custom_str4 = 'User';
    $payfast->custom_str5 = 'ClothingOrder';
    $payfast->notify_url = 'https://www.capetennis.co.za/notifyClothing';
   // dd($payfast);
    // AJAX support unchanged
    if ($request->ajax() || $request->wantsJson()) {
      return response()->json([
        'ok' => true,
        'orderId' => $order->id,
        'total' => $total,
        'cartUrl' => route('events.show', $validated['event_id']),
      ]);
    }

    // NORMAL WEB FLOW
    return view('frontend.clothing.cart-clothing', [
      'items' => $orderItems,
      'payfast' => $payfast,
      'order' => $order,
      'total' => $total,
    ]);



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

  public function showRegionClothing($id)
  {
      $teams = TeamRegion::findOrFail($id)
                 ->teams
                 ->pluck('id');

      $clothings = ClothingOrder::with([
          'player',
          'team',
          'items',           // ← load the items themselves
          'items.size',      // ← then size on each item
          'items.itemType',
          'transaction' // ← and itemType (for price, name, etc.)
      ])
      ->whereIn('team_id', $teams)
      ->where('pay_status', 1)
      ->get();

  
      return view('backend.clothing.clothing-index', [
          'clothings' => $clothings,
          'region'    => $id,
      ]);
  }




  public function exportPdf($id)
  {

    $teams = TeamRegion::find($id)->teams->pluck('id');

    $clothings = ClothingOrder::whereIn('team_id', $teams)
      ->where('pay_status', 1)
      ->get();

    //dd($clothings[0]);
    // Load view and pass data
    $pdf = Pdf::loadView('.backend.clothing.clothing-order-pdf', compact('clothings'));

    // Download the PDF
    return $pdf->download('clothing_orders.pdf');
  }


  public function exportExcel($id)
  {
    $teams = TeamRegion::find($id)->teams->pluck('id');

    $clothings = ClothingOrder::whereIn('team_id', $teams)
      ->where('pay_status', 1)
      ->get();


    // Pass data to the export class
    return Excel::download(new ClothingOrdersExport($clothings), 'clothing_orders.xlsx');
  }

  public function sheet(TeamRegion $region, Request $request)
  {
    // Load items with sizes; adapt to your relationships
    // Example Eloquent shape: $region->clothingItems()->with('sizes')->orderBy('ordering')->get()
    $items = $region->clothingItems()
      ->with(['sizes' => function ($q) {
        $q->orderBy('ordering')->orderBy('size'); }])
      ->orderBy('ordering')
      ->get(['id', 'item_type_name', 'price', 'ordering']);

    // Map to JSON expected by the JS
    $data = $items->map(function ($it) {
      return [
        'id' => $it->id,
        'item_type_name' => $it->item_type_name,
        'price' => (float) ($it->price ?? 0),
        'ordering' => $it->ordering,
        'sizes' => $it->sizes->map(fn($s) => [
          'id' => $s->id,
          'size' => $s->size,
          'ordering' => $s->ordering,
        ])->values()
      ];
    })->values();

    return response()->json(['items' => $data]);
  }

  public function place(TeamRegion $region, Request $request)
  {
    $validated = $request->validate([
      'region_id' => 'required|integer',
      'team_id' => 'required|integer',
      'player_id' => 'required|integer',
      'lines' => 'required|array|min:1',
      'lines.*.item_id' => 'required|integer',
      'lines.*.size_id' => 'required|integer',
      'lines.*.qty' => 'required|integer|min:1',
      'lines.*.price' => 'nullable|numeric',
    ]);

    // TODO: Save order + order_lines in your DB (create tables if needed)
    // Example pseudo:
    // $order = ClothingOrder::create([...]);
    // foreach ($validated['lines'] as $l) { ClothingOrderLine::create([...]); }

    return response()->json(['ok' => true, 'message' => 'Order saved']);
  }



  public function toggleClothingOrder($id)
  {
    $region = TeamRegion::findOrFail($id);

    if (is_null($region->clothing_order)) {
      $region->clothing_order = 0;
    } else {
      $region->clothing_order = $region->clothing_order == 1 ? 0 : 1;
    }

    $region->save();

    return response()->json([
      'success' => true,
      'state' => $region->clothing_order,
      'message' => $region->clothing_order
        ? 'Clothing order reopened'
        : 'Clothing order closed',
    ]);
  }




}
