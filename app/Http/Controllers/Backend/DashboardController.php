<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Player;
use App\Models\User;
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

    // Only needed for the sidebar player-link modal
    $players = Player::select('id', 'name', 'surname', 'email')->get();

    // Only needed for the create-event modal (super-admin)
    $eventTypes = $user->can('superUser') ? EventType::all() : collect();
    $users      = $user->can('superUser') ? User::select('id', 'name')->orderBy('name')->get() : collect();

    // Wallet transactions
    $wallet = $user->wallet;
    $transactions = $wallet ? $wallet->transactions()->latest()->get() : collect();

    // Activity log (last 50 entries for super users)
    $activityLogs = $user->can('superUser')
      ? Activity::with('causer')
          ->latest()
          ->limit(50)
          ->get()
      : collect();

    // Determine which tabs to show per user
    $tabs = [
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
      'players',
      'eventTypes',
      'users',
      'transactions',
      'activityLogs',
      'activityByUser',
      'logNames',
      'tabs'
    ));
  }
}
