<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Player;
use App\Models\Registration;
use App\Models\User;
use App\Models\Withdrawals;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class SuperAdminDashboardController extends Controller
{
  public function index()
  {
    // ── Top stat cards ──────────────────────────────────────────────────
    $totalUsers         = User::count();
    $totalPlayers       = Player::count();
    $totalEvents        = Event::count();
    $activeEvents       = Event::where('start_date', '<=', Carbon::today())
                               ->where('end_date', '>=', Carbon::today())
                               ->count();
    $totalRegistrations = Registration::count();

    // ── User growth ──────────────────────────────────────────────────────
    $newUsersThisMonth  = User::where('created_at', '>=', Carbon::now()->startOfMonth())->count();
    $newUsersThisWeek   = User::where('created_at', '>=', Carbon::now()->startOfWeek())->count();
    $newPlayersThisMonth = Player::where('created_at', '>=', Carbon::now()->startOfMonth())->count();
    $newPlayersThisWeek  = Player::where('created_at', '>=', Carbon::now()->startOfWeek())->count();

    // ── Recent registrations ─────────────────────────────────────────────
    $recentRegistrations = Registration::where('created_at', '>=', Carbon::now()->subDays(30))->count();
    $pendingWithdrawals  = Withdrawals::count();

    // ── Player Profile Status ─────────────────────────────────────────────
    // Up to Date: all key fields present AND profile was updated after creation
    $profileUpToDate = Player::whereNotNull('dateOfBirth')
        ->whereNotNull('email')
        ->whereNotNull('gender')
        ->whereColumn('updated_at', '>', 'created_at')
        ->count();

    // Never Updated: updated_at has not moved beyond created_at (profile untouched)
    $profileNeverUpdated = Player::whereColumn('updated_at', '<=', 'created_at')->count();

    // Incomplete: missing one or more required fields
    $profileIncomplete = Player::where(function ($q) {
        $q->whereNull('dateOfBirth')
          ->orWhereNull('email')
          ->orWhereNull('gender');
    })->count();

    // Needs Update: has been updated at least once but still missing a field
    $profileNeedsUpdate = Player::whereColumn('updated_at', '>', 'created_at')
        ->where(function ($q) {
            $q->whereNull('dateOfBirth')
              ->orWhereNull('email')
              ->orWhereNull('gender');
        })->count();

    // ── Activity Log (Spatie) ─────────────────────────────────────────────
    $activityLogs = Activity::with('causer')
        ->latest()
        ->limit(100)
        ->get();

    $activityByUser = collect();
    if ($activityLogs->isNotEmpty()) {
      $activityByUser = $activityLogs
          ->groupBy(fn($a) => $a->causer_id ?? 'system')
          ->map(function ($group) {
            $first = $group->first();
            return (object) [
              'causer'              => $first->causer,
              'causer_id'           => $first->causer_id,
              'count'               => $group->count(),
              'last_at'             => $group->sortByDesc('created_at')->first()->created_at,
              'example_description' => $group->sortByDesc('created_at')->first()->description,
              'properties'          => $group->sortByDesc('created_at')->first()->properties ?? null,
              'log_names'           => $group->pluck('log_name')->unique()->values()->toArray(),
            ];
          })->values();
    }
    $logNames = $activityLogs->pluck('log_name')->unique()->values()->toArray();

    // ── Login Audit (Rappasoft authentication_log) ────────────────────────
    $loginAuditLogs = DB::table('authentication_log as al')
        ->join('users as u', 'u.id', '=', 'al.authenticatable_id')
        ->where('al.authenticatable_type', 'like', '%User%')
        ->select(
            'al.id',
            'u.name',
            'u.email',
            'al.ip_address',
            'al.user_agent',
            'al.login_at',
            'al.logout_at',
            'al.login_successful'
        )
        ->orderByDesc('al.login_at')
        ->limit(50)
        ->get();

    $loginAuditTodayCount  = DB::table('authentication_log')
        ->where('login_at', '>=', Carbon::today())
        ->where('login_successful', true)
        ->count();
    $loginAuditFailedToday = DB::table('authentication_log')
        ->where('login_at', '>=', Carbon::today())
        ->where('login_successful', false)
        ->count();

    return view('backend.superAdminDashboard', compact(
      'totalUsers',
      'totalPlayers',
      'totalEvents',
      'activeEvents',
      'totalRegistrations',
      'newUsersThisMonth',
      'newUsersThisWeek',
      'newPlayersThisMonth',
      'newPlayersThisWeek',
      'recentRegistrations',
      'pendingWithdrawals',
      'profileUpToDate',
      'profileNeverUpdated',
      'profileIncomplete',
      'profileNeedsUpdate',
      'activityLogs',
      'activityByUser',
      'logNames',
      'loginAuditLogs',
      'loginAuditTodayCount',
      'loginAuditFailedToday'
    ));
  }
}
