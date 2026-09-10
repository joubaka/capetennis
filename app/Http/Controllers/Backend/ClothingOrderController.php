<?php

namespace App\Http\Controllers\Backend;

use App\Services\Payfast;
use App\Exports\ClothingOrdersExport;
use App\Http\Controllers\Controller;
use App\Models\ClothingItemType;
use App\Models\ClothingOrder;
use App\Models\ClothingOrderItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TeamRegion;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Services\Clothing\ClothingOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;


class ClothingOrderController extends Controller
{
  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index()
  {
    abort_unless(request()->user()?->hasRole('super-user'), 403);
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



  public function store(Request $request, ClothingOrderService $orders)
  {
    $validated = $request->validate([
      'player_id' => ['required', 'integer', 'exists:players,id'],
      'team_id' => ['required', 'integer', 'exists:teams,id'],
      'event_id' => ['required', 'integer', 'exists:events,id'],
      'region_id' => ['required', 'integer', 'exists:team_regions,id'],
      'request_token' => ['required', 'uuid'],
      'items' => ['required', 'array', 'min:1', 'max:20'],
      'items.*.size' => ['required', 'integer', 'exists:clothing_sizes,id'],
      'items.*.qty' => ['required', 'integer', 'min:1', 'max:20'],
    ]);

    $event = Event::findOrFail($validated['event_id']);
    $team = Team::findOrFail($validated['team_id']);
    $player = Player::findOrFail($validated['player_id']);
    $region = TeamRegion::findOrFail($validated['region_id']);
    $order = $orders->create(
      $request->user(), $event, $region, $team, $player,
      $validated['items'], $validated['request_token']
    )->load('items');

    $payfast = new Payfast();
    if (app()->environment(['local', 'testing'])) $payfast->setMode(0);
    $payfast->setReturnUrl(route('events.show', $event));
    $payfast->setCancelUrl(route('events.show', $event));
    $payfast->setNotifyUrl(route('notify.clothing'));
    $payfast->setItem('Clothing Order #' . $order->id);
    $payfast->setAmount((float) $order->payfast_amount_due);
    $payfast->custom_int1 = $team->id;
    $payfast->custom_int2 = $player->id;
    $payfast->custom_int3 = $event->id;
    $payfast->custom_int4 = $request->user()->id;
    $payfast->custom_int5 = (int) $order->id;
    $payfast->custom_str1 = 'Team';
    $payfast->custom_str2 = 'Player';
    $payfast->custom_str3 = 'Event';
    $payfast->custom_str4 = 'User';
    $payfast->custom_str5 = 'ClothingOrder';

    if ($request->ajax() || $request->wantsJson()) {
      return response()->json([
        'ok' => true,
        'orderId' => $order->id,
        'total' => (float) $order->total,
        'subtotal' => (float) $order->subtotal,
        'payfastFee' => (float) $order->payfast_fee,
        'cartUrl' => route('events.show', $event),
      ]);
    }

    return view('frontend.clothing.cart-clothing', [
      'items' => $this->customerFacingItems($order),
      'payfast' => $payfast,
      'order' => $order,
      'total' => (float) $order->total,
      'subtotal' => (float) $order->subtotal,
      'payfastFee' => (float) $order->payfast_fee,
    ]);
  }

  /**
   * Older pending orders snapshot the internal amount on each line. Present
   * those rows using the already-locked payable total without mutating history.
   */
  private function customerFacingItems(ClothingOrder $order): \Illuminate\Support\Collection
  {
    $items = $order->items->map(fn (ClothingOrderItem $item) => clone $item)->values();
    $storedTotal = round((float) $items->sum(fn (ClothingOrderItem $item) => (float) $item->line_total), 2);
    $payableTotal = round((float) $order->total, 2);

    if ($items->isEmpty() || abs($storedTotal - $payableTotal) < 0.01 || $storedTotal <= 0) {
      return $items;
    }

    $remaining = $payableTotal;
    $items->each(function (ClothingOrderItem $item, int $index) use ($items, $storedTotal, $payableTotal, &$remaining): void {
      $lineTotal = $index === $items->count() - 1
        ? $remaining
        : round($payableTotal * ((float) $item->line_total / $storedTotal), 2);
      $quantity = max(1, (int) $item->qty);
      $item->line_total = round($lineTotal, 2);
      $item->price = round($lineTotal / $quantity, 2);
      $remaining = round($remaining - $lineTotal, 2);
    });

    return $items;
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
      $region = TeamRegion::findOrFail($id);
      $this->authorize('region-clothing.manage', $region);
      $teams = $region->teams
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
          'region'    => $region,
      ]);
  }




  public function exportPdf($id)
  {
    $region = TeamRegion::findOrFail($id);
    $this->authorize('region-clothing.manage', $region);
    $teams = $region->teams->pluck('id');

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
    $region = TeamRegion::findOrFail($id);
    $this->authorize('region-clothing.manage', $region);
    $teams = $region->teams->pluck('id');

    $clothings = ClothingOrder::whereIn('team_id', $teams)
      ->where('pay_status', 1)
      ->get();


    // Pass data to the export class
    return Excel::download(new ClothingOrdersExport($clothings), 'clothing_orders.xlsx');
  }

  public function sheet(TeamRegion $region, Request $request)
  {
    abort_unless($region->usesOnlineClothingOrders() && (bool) $region->clothing_order, 404);
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

  public function toggleClothingOrder($id)
  {
    $region = TeamRegion::findOrFail($id);
    $this->authorize('region-clothing.manage', $region);

    if (! $region->clothing_order) {
      abort_unless($region->usesOnlineClothingOrders(), 422, 'Select this region for online clothing orders in the event clothing setup first.');
      $items = $region->clothingItems()->withCount('sizes')->get();
      abort_if($items->isEmpty(), 422, 'Add clothing items before opening orders.');
      abort_if($items->contains(fn ($item) => (float) $item->price <= 0 || (int) $item->sizes_count === 0), 422,
        'Every clothing item needs an approved price and at least one size before opening orders.');
    }

    $region->clothing_order = ! (bool) $region->clothing_order;

    $region->save();

    $payload = [
      'success' => true,
      'state' => $region->clothing_order,
      'message' => $region->clothing_order
        ? 'Clothing order reopened'
        : 'Clothing order closed',
    ];

    return request()->expectsJson()
      ? response()->json($payload)
      : back()->with('success', $payload['message'].'.');
  }




}
