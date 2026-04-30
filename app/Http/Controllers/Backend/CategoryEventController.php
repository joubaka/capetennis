<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\DrawFormats;
use App\Models\CategoryEventRegistration;
use Illuminate\Http\Request;

class CategoryEventController extends Controller
{
  public function manage($category_event_id)
  {
      $categoryEvent = CategoryEvent::with([
          'category',
          'draws.settings',
          'draws.groups.registrations.players',
          'draws.registrations.players',
          'draws.drawFormat',
          'registrations.players'
      ])->findOrFail($category_event_id);

      $eligibleRegistrations = $categoryEvent
          ->registrations
          ->filter(fn($reg) => $reg->draws->isEmpty());

      // ✅ Send all registrations
      $allRegistrations = $categoryEvent->registrations;

      $drawFormats = DrawFormats::all();

      return view('backend.categoryEvent.manage', compact(
          'categoryEvent',
          'eligibleRegistrations',
          'allRegistrations',
          'drawFormats'
      ));
  }
  public function withdraw(CategoryEventRegistration $registration)
  {
    $user = auth()->user();

    if ($registration->status === 'withdrawn') {
      return back()->withErrors('This registration is already withdrawn.');
    }

    $player = $registration->players->first();
    $eventName = optional($registration->categoryEvent?->event)->name ?? 'Event';
    $categoryName = optional($registration->categoryEvent?->category)->name ?? '';

    $registration->update([
      'status'        => 'withdrawn',
      'withdrawn_at'  => now(),
      'refund_status' => 'not_refunded',
      'refund_method' => null,
      'refund_gross'  => 0,
      'refund_fee'    => 0,
      'refund_net'    => 0,
      'refunded_at'   => null,
    ]);

    activity('withdrawal')
      ->performedOn($registration)
      ->causedBy($user)
      ->withProperties([
        'registration_id' => $registration->id,
        'event'           => $eventName,
        'category'        => $categoryName,
        'player'          => $player ? trim($player->name . ' ' . $player->surname) : '',
        'initiated_by'    => 'admin',
      ])
      ->log("Admin withdrew {$eventName} ({$categoryName})");

    if ($registration->is_paid) {
      $event = $registration->categoryEvent->event;
      return redirect()
        ->route('admin.registration.refund.choose', [$event, $registration])
        ->with('success', 'Registration withdrawn. Please choose a refund method.');
    }

    return back()->with('success', 'Registration withdrawn (not paid — no refund required).');
  }

}
