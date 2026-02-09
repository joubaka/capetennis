<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use Illuminate\Http\Request;

class BankRefundController extends Controller
{
  /**
   * List pending bank refunds
   */
  public function index()
  {
    $refunds = CategoryEventRegistration::with([
      'categoryEvent.event',
      'players',
      'registration',
      'user',
    ])
      ->where('status', 'withdrawn')
      ->where('refund_method', 'bank')
      ->where('refund_status', 'pending')
      ->orderBy('updated_at')
      ->get();

    return view('backend.refunds.bank', compact('refunds'));
  }

  /**
   * Mark bank refund as completed
   */
  public function complete(CategoryEventRegistration $registration)
  {
    if ($registration->refund_method !== 'bank') {
      return back()->withErrors('Invalid refund type.');
    }

    if ($registration->refund_status !== 'pending') {
      return back()->withErrors('Refund already processed.');
    }

    $registration->update([
      'refund_status' => 'completed',
      'refunded_at' => now(),
    ]);

    return back()->with('success', 'Bank refund marked as completed.');
  }
}
