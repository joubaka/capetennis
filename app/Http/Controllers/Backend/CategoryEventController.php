<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Entries\Services\EntryService;
use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\DrawFormats;
use App\Models\DrawType;
use App\Models\CategoryEventRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
      $drawTypes = DrawType::query()->orderBy('drawTypeName')->get();

      return view('backend.categoryEvent.manage', compact(
          'categoryEvent',
          'eligibleRegistrations',
          'allRegistrations',
          'drawFormats',
          'drawTypes'
      ));
  }
  public function withdraw(CategoryEventRegistration $registration)
  {
    $user = auth()->user();

    if (!$user || !$user->hasAnyRole(['super-user', 'admin', 'convenor'])) {
      abort(403, 'Unauthorized.');
    }

    if ($registration->status === 'withdrawn') {
      return back()->withErrors('This registration is already withdrawn.');
    }

    $player = $registration->players->first();
    $eventName = optional($registration->categoryEvent?->event)->name ?? 'Event';
    $categoryName = optional($registration->categoryEvent?->category)->name ?? '';

    // HOTFIX 3: Route through EntryService::withdrawEntryAsAdmin() so that
    // draw_group_registrations are removed atomically inside the transaction.
    app(EntryService::class)->withdrawEntryAsAdmin($registration, $user);

    // Send notification emails outside the transaction
    $registration->sendWithdrawalEmails('admin');

    if ($registration->is_paid) {
      $event = $registration->categoryEvent->event;

      // Admins may always remove the entry, but a refund is only available
      // while the event withdrawal window is open.  The admin service
      // intentionally bypasses the deadline for the withdrawal itself.
      $refundAllowed = now()->lte($event->withdrawalCloseAt());

      // Keep the Masters invitation lifecycle in sync for admin withdrawals
      // as well as player self-withdrawals (including replacement handling).
      app(\App\Services\Masters\MastersInvitationService::class)
        ->handlePaidWithdrawal((int) $registration->id, $user);

      // Only super-users may issue refunds, and only before the deadline.
      if ($refundAllowed && ($user->can('super-user') || (method_exists($user, 'hasRole') && $user->hasRole('super-user')))) {
        $refundUrl = route('admin.registration.refund.choose', [$event, $registration]);
        if (request()->ajax() || request()->wantsJson()) {
          return response()->json(['success' => true, 'redirect' => $refundUrl]);
        }
        return redirect()->to($refundUrl)->with('success', 'Registration withdrawn. Please choose a refund method.');
      }

      if (request()->ajax() || request()->wantsJson()) {
        return response()->json(['success' => true]);
      }
      return back()->with('success', $refundAllowed
        ? 'Registration withdrawn (no refund issued).'
        : 'Registration withdrawn (no refund — deadline passed).');
    }

    if (request()->ajax() || request()->wantsJson()) {
      return response()->json(['success' => true]);
    }
    return back()->with('success', 'Registration withdrawn (not paid — no refund required).');
  }

}
