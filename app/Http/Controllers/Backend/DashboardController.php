<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventType;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
  public function dashboard()
  {
    $user = Auth::user()->load([
      'wallet',
      'players',
    ]);

    $managedEventsQuery = Event::query()
      ->when(
        ! $user->hasRole('super-user'),
        fn ($query) => $query->whereHas('admins', fn ($admins) => $admins->where('users.id', $user->id))
      );
    $managedEventCount = (clone $managedEventsQuery)->count();
    $managedEvents = $managedEventsQuery
      ->with(['eventTypeModel', 'series'])
      ->orderByRaw('CASE WHEN COALESCE(events.end_date, events.start_date) >= ? THEN 0 ELSE 1 END', [today()->toDateString()])
      ->orderBy('events.start_date')
      ->orderBy('events.id')
      ->simplePaginate(12, ['*'], 'event_page')
      ->withQueryString();

    // Public discovery remains stricter than administrator visibility: even
    // assigned admins and super-users only see published events here.
    $upcomingEvents = Event::query()
      ->select(['id', 'name', 'start_date', 'end_date', 'eventType', 'series_id'])
      ->where('published', true)
      ->upcoming()
      ->with([
        'eventTypeModel:id,name',
        'series:id,name',
      ])
      ->orderBy('start_date')
      ->orderBy('id')
      ->limit(6)
      ->get();

    // Only needed for the create-event modal (super-admin)
    $eventTypes = $user->can('superUser') ? EventType::all() : collect();

    // Keep the dashboard bounded; the wallet balance remains ledger-derived.
    $wallet = $user->wallet;
    $transactions = WalletTransaction::query()
      ->when(
        $wallet,
        fn ($query) => $query->where('wallet_id', $wallet->id),
        fn ($query) => $query->whereRaw('1 = 0')
      )
      ->latest()
      ->paginate(10, ['*'], 'wallet_page');

    // Activity log (last 50 entries for super users)
    $activityLogs = $user->can('superUser')
      ? Activity::with('causer')
          ->latest()
          ->limit(50)
          ->get()
      : collect();

    // Determine which tabs to show per user
    $tabs = [
      'events' => $user->hasRole('super-user') || $managedEventCount > 0,
      'rankings' => $user->can('superUser'),
      'users' => $user->can('superUser'),
      // players visible to admins and super-users
      'players' => (method_exists($user, 'hasRole') && ($user->hasRole('admin') || $user->hasRole('super-user'))) || $user->can('superUser'),
      // activity visible to super-users and admins
      'activity' => (method_exists($user, 'hasRole') && ($user->hasRole('admin') || $user->hasRole('super-user'))) || $user->can('superUser'),
    ];

    // Group activity by causer (user) for admin view — groups only within the last 50 entries
    $activityByUser = collect();
    if ($activityLogs->isNotEmpty()) {
      $activityByUser = $activityLogs
        ->groupBy(fn($a) => $a->causer_id ?? 'system')
        ->map(function ($group, $causerId) {
          $first = $group->first();
          return (object) [
            'causer' => $first->causer,
            'causer_id' => $causerId,
            'count' => $group->count(),
            'last_at' => $group->sortByDesc('created_at')->first()->created_at,
            'example_description' => $group->sortByDesc('created_at')->first()->description,
            'properties' => $group->sortByDesc('created_at')->first()->properties ?? null,
            'log_names' => $group->pluck('log_name')->unique()->values()->toArray(),
          ];
        })->values();
    }

    // Distinct log names for filter dropdown
    $logNames = $activityLogs->pluck('log_name')->unique()->values()->toArray();

    return view('backend.dashboard', compact(
      'user',
      'eventTypes',
      'transactions',
      'activityLogs',
      'activityByUser',
      'logNames',
      'tabs',
      'managedEvents',
      'managedEventCount',
      'upcomingEvents'
    ));
  }
}
