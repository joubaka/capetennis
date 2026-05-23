<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Entries\Services\EntryService;
use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\DrawFormats;
use App\Models\CategoryEventRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryEventController extends Controller
{
  public function __construct(private EntryService $entryService) {}

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

    try {
      $this->entryService->withdrawEntryAsAdmin($registration, $user);
    } catch (\RuntimeException $e) {
      if (request()->ajax() || request()->wantsJson()) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
      }
      return back()->withErrors($e->getMessage());
    }

    // Send notification emails outside the transaction
    $registration->sendWithdrawalEmails('admin');

    if ($registration->is_paid) {
      $event = $registration->categoryEvent->event;

      if ($user->can('super-user') || (method_exists($user, 'hasRole') && $user->hasRole('super-user'))) {
        $refundUrl = route('admin.registration.refund.choose', [$event, $registration]);
        if (request()->ajax() || request()->wantsJson()) {
          return response()->json(['success' => true, 'redirect' => $refundUrl]);
        }
        return redirect()->to($refundUrl)->with('success', 'Registration withdrawn. Please choose a refund method.');
      }

      if (request()->ajax() || request()->wantsJson()) {
        return response()->json(['success' => true]);
      }
      return back()->with('success', 'Registration withdrawn (no refund issued).');
    }

    if (request()->ajax() || request()->wantsJson()) {
      return response()->json(['success' => true]);
    }
    return back()->with('success', 'Registration withdrawn (not paid — no refund required).');
  }

  public function reinstate(CategoryEventRegistration $registration)
  {
    if ($registration->status !== 'withdrawn') {
      if (request()->ajax() || request()->wantsJson()) {
        return response()->json(['success' => false, 'message' => 'Registration is not withdrawn.'], 422);
      }
      return back()->withErrors('Registration is not withdrawn.');
    }

    DB::transaction(function () use ($registration) {
      $registration->update([
        'status'        => 'active',
        'withdrawn_at'  => null,
        'refund_status' => 'not_refunded',
      ]);
    });

    if (request()->ajax() || request()->wantsJson()) {
      return response()->json(['success' => true, 'message' => 'Player reinstated successfully.']);
    }
    return back()->with('success', 'Player reinstated successfully.');
  }

}
