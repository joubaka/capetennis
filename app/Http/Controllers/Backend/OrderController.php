<?php

namespace App\Http\Controllers\backend;

use App\Classes\Payfast;
use App\Http\Controllers\Controller;
use App\Models\ClothingItemType;
use App\Models\ClothingOrder;
use App\Models\ClothingOrderItems;
use App\Models\ClothingSize;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
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


    return $request;
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
  public function showHoodieForm()
  {
    $items = ClothingItemType::whereIn('id', [75, 76])->get();
    $sizes = ClothingSize::where('item_type', 75)->get();
    // dd($sizes);
    return view('orders.hoodie-form', compact('items', 'sizes'));
  }

  public function submitHoodieForm(Request $request)
  {

    $validated = $request->validate([
      'items' => 'required|array',
      'items.*' => 'exists:clothing_item_types,id',
      'sizes' => 'required|array',
      'sizes.*' => 'exists:clothing_sizes,id',
      'name' => 'required|string',
      'email' => 'required|email',
      'town' => 'required|string' ,
    ]);

    $order = ClothingOrder::create([
      'team_id' => 10000,
      'email_text' => $validated['email'],
      'user_text' => $validated['name'],
      'pay_status' => 0,
      'user_id' => 0,
      'town_text' => $validated['town'],
    ]);

    $total = 0;

    foreach ($validated['items'] as $index => $itemId) {
      $item = ClothingItemType::find($itemId);
      $sizeId = $validated['sizes'][$index];

      $order->items()->create([
        'clothing_order_item_id' => $itemId,
        'clothing_item_size' => $sizeId,
      ]);

      $total += $item->price;
    }



    $pf = new Payfast();
    $pf->setNotifyUrl('notify_hoodie');
    $pf->setAmount($total);
    $pf->setItem('JTA Hoodie');
    $mode = 'live';

    //0 for sandbox and 1 for live

    if (Auth::check() && Auth::user()->id == 584) {
      if ($mode == 'live') {
        $pf->setMode(0);


      } else {
        $pf->setMode(1);

      }
    } else {
      $pf->setMode(1);
       $pf->merchant_id = $pf->payfast_id;
        $pf->merchant_key = $pf->payfast_key;
    }

    //event fee and name
 $pf->merchant_id = $pf->id;
        $pf->merchant_key = $pf->key;
$pf->custom_str1 =  $validated['name'];
$pf->custom_str2 = $validated['email'];
$pf->custom_str5 = 'order number';
$pf->custom_int5 = $order->id;
$pf->setReturnUrl('/hoodie/orders/paid');

    return redirect()->away($pf->url . '?' . http_build_query($pf));
  }


  public function getSizesForItem($item_id)
  {

    $sizes = ClothingSize::where('item_type', $item_id)
      ->orderBy('ordering')
      ->get();

    return response()->json($sizes);
  }

  public function notifyHoodie(){


        // Tell PayFast that this page is reachable by triggering a header 200
        header('HTTP/1.0 200 OK');
        flush();
        $order = ClothingOrder::find($_POST['custom_int5']);
        $order->pay_status = 1;
        $order->pf_id = $_POST['pf_payment_id'];
        $order->save();

  }

  public function paidOrders()
{
  $orders = \App\Models\ClothingOrder::with(['items.itemType', 'items.size'])
    ->where('pay_status', 1)
    ->whereHas('items', function ($query) {
        $query->whereIn('clothing_order_item_id', [75, 76]); // <-- corrected column
    })
    ->orderByDesc('created_at')
    ->get();



    return view('orders.paid_orders', compact('orders'));
}


}
