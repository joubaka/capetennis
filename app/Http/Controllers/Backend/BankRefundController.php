<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use App\Models\TeamPaymentOrder;
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

    $completedRefunds = CategoryEventRegistration::with([
      'categoryEvent.event',
      'players',
      'registration',
      'user',
    ])
      ->where('refund_method', 'bank')
      ->where('refund_status', 'completed')
      ->orderBy('updated_at')
      ->get();

    // Team refunds
    $pendingTeamRefunds = TeamPaymentOrder::with(['team', 'player', 'user', 'event'])
      ->where('refund_method', 'bank')
      ->where('refund_status', 'pending')
      ->orderBy('updated_at')
      ->get();

    $completedTeamRefunds = TeamPaymentOrder::with(['team', 'player', 'user', 'event'])
      ->where('refund_method', 'bank')
      ->where('refund_status', 'completed')
      ->orderBy('updated_at')
      ->get();

    \Log::debug('BACKEND BANK INDEX counts', [
      'pending_registration_refunds' => $refunds->count(),
      'pending_team_refunds' => $pendingTeamRefunds->count(),
    ]);

    try {
      \Log::debug('BACKEND BANK INDEX team refunds data', [
        'team_refunds' => $pendingTeamRefunds->map(function ($r) {
          return [
            'id' => $r->id,
            'team_id' => $r->team_id,
            'player_id' => $r->player_id,
            'event_id' => $r->event_id,
            'refund_status' => $r->refund_status,
            'refund_net' => $r->refund_net,
            'refund_account_name' => $r->refund_account_name,
            'refund_bank_name' => $r->refund_bank_name,
            'updated_at' => optional($r->updated_at)->toDateTimeString(),
          ];
        })->toArray()
      ]);
    } catch (\Throwable $e) {
      \Log::error('BACKEND BANK INDEX debug dump failed', ['error' => $e->getMessage()]);
    }

    return view('backend.refunds.bank', compact('refunds', 'completedRefunds', 'pendingTeamRefunds', 'completedTeamRefunds'));
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

  /**
   * Mark a team bank refund as completed
   */
  public function completeTeam(TeamPaymentOrder $order)
  {
    if ($order->refund_method !== 'bank') {
      return back()->withErrors('Invalid refund type.');
    }

    if ($order->refund_status !== 'pending') {
      return back()->withErrors('Refund already processed.');
    }

    $order->update([
      'refund_status' => 'completed',
      'refunded_at' => now(),
    ]);

    return back()->with('success', 'Team bank refund marked as completed.');
  }
}
