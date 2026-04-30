<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\Event;
use App\Models\EventExpense;
use App\Models\EventIncomeItem;
use App\Models\Player;
use App\Models\PlayerAgreement;
use App\Models\Registration;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawals;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Spatie\Activitylog\Models\Activity;

class SuperAdminController extends Controller
{
    /**
     * Show the consolidated Super Admin Dashboard.
     */
    public function index()
    {
        $oneYearAgo = Carbon::now()->subYear();

        // ── Top stat cards ──────────────────────────────────────────────────
        $totalUsers          = User::count();
        $totalPlayers        = Player::count();
        $totalEvents         = Event::count();
        $activeEvents        = Event::where('start_date', '<=', Carbon::today())
                                    ->where('end_date', '>=', Carbon::today())
                                    ->count();
        $totalRegistrations  = Registration::count();
        $recentRegistrations = Registration::where('created_at', '>=', Carbon::now()->subDays(30))->count();

        // ── User / Player growth ─────────────────────────────────────────────
        $newUsersThisWeek    = User::where('created_at', '>=', Carbon::now()->startOfWeek())->count();
        $newUsersThisMonth   = User::where('created_at', '>=', Carbon::now()->startOfMonth())->count();
        $newPlayersThisWeek  = Player::where('created_at', '>=', Carbon::now()->startOfWeek())->count();

        // ── Pending withdrawals ──────────────────────────────────────────────
        $pendingWithdrawals = Withdrawals::count();

        // ── Agreement statistics ─────────────────────────────────────────────
        $activeAgreement = Agreement::where('is_active', 1)->latest()->first();
        $agreementStats  = [
            'total_agreements'  => Agreement::count(),
            'active_agreement'  => $activeAgreement,
            'total_acceptances' => $activeAgreement
                ? PlayerAgreement::where('agreement_id', $activeAgreement->id)->count()
                : 0,
            'pending_players'   => $activeAgreement
                ? Player::whereDoesntHave('agreements', function ($q) use ($activeAgreement) {
                      $q->where('agreement_id', $activeAgreement->id);
                  })->count()
                : 0,
        ];

        // ── Player Profile Status ─────────────────────────────────────────────
        $profileStats = [
            'up_to_date'    => Player::where('profile_complete', true)
                                     ->where('profile_updated_at', '>=', $oneYearAgo)
                                     ->count(),
            'needs_update'  => Player::where(function ($q) use ($oneYearAgo) {
                                   $q->whereNull('profile_updated_at')
                                     ->orWhere('profile_updated_at', '<', $oneYearAgo);
                               })->count(),
            'incomplete'    => Player::where(function ($q) {
                                   $q->where('profile_complete', false)
                                     ->orWhereNull('profile_complete');
                               })->count(),
            'never_updated' => Player::whereNull('profile_updated_at')->count(),
        ];

        // ── Players needing attention ─────────────────────────────────────────
        $playersNeedingAttention = Player::where(function ($q) use ($oneYearAgo) {
            $q->whereNull('profile_updated_at')
              ->orWhere('profile_updated_at', '<', $oneYearAgo)
              ->orWhere('profile_complete', false)
              ->orWhereNull('profile_complete');
        })
        ->with('user')
        ->orderBy('profile_updated_at', 'asc')
        ->limit(15)
        ->get();

        // ── All agreements for management table ───────────────────────────────
        $agreements = Agreement::withCount('playerAgreements')->orderByDesc('created_at')->get();

        // ── Recent users ──────────────────────────────────────────────────────
        $recentUsers = User::orderByDesc('created_at')->limit(10)->get();

        // ── Activity Log (Spatie) ─────────────────────────────────────────────
        $activityLogs = Activity::with('causer')->latest()->limit(100)->get();

        $activityByUser = collect();
        if ($activityLogs->isNotEmpty()) {
            $activityByUser = $activityLogs
                ->groupBy(fn ($a) => $a->causer_id ?? 'system')
                ->map(function ($group) {
                    $first = $group->first();
                    return (object) [
                        'causer'              => $first->causer,
                        'causer_id'           => $first->causer_id,
                        'count'               => $group->count(),
                        'last_at'             => $group->sortByDesc('created_at')->first()->created_at,
                        'example_description' => $group->sortByDesc('created_at')->first()->description,
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

        // ── Financial Dashboard (all events) ─────────────────────────────────
        $allEvents = Event::with(['incomeItems', 'expenses'])->orderByDesc('start_date')->get();

        $feesByEvent = Transaction::where('transaction_type', 'Registration')
            ->where('amount_gross', '>', 0)
            ->selectRaw('event_id, SUM(amount_gross) as total_gross, SUM(amount_fee) as total_fee')
            ->groupBy('event_id')
            ->get()
            ->keyBy('event_id');

        $entriesByEvent = Transaction::where('transaction_type', 'Registration')
            ->where('amount_gross', '>', 0)
            ->join('registration_orders', 'transactions_pf.custom_int5', '=', 'registration_orders.id')
            ->join('registration_order_items', 'registration_orders.id', '=', 'registration_order_items.order_id')
            ->selectRaw('transactions_pf.event_id, COUNT(registration_order_items.id) as total_entries')
            ->groupBy('transactions_pf.event_id')
            ->get()
            ->keyBy('event_id');

        $financeByEvent = $allEvents->map(function ($event) use ($feesByEvent, $entriesByEvent) {
            $feePerEntry   = (float) $event->cape_tennis_fee;
            $txRow         = $feesByEvent->get($event->id);
            $totalGross    = $txRow ? (float) $txRow->total_gross : 0;
            $totalPfFee    = $txRow ? abs((float) $txRow->total_fee) : 0;
            $totalEntries  = $entriesByEvent->get($event->id)?->total_entries ?? 0;
            $ctFee         = $totalEntries * $feePerEntry;
            $netReg        = $totalGross - $totalPfFee - $ctFee;
            $incomeItems   = $event->incomeItems->sum(fn ($i) => $i->calculatedTotal());
            $totalIncome   = $netReg + $incomeItems;
            $totalExpenses = $event->expenses->sum(fn ($e) => $e->calculatedAmount());
            $netProfit     = $totalIncome - $totalExpenses;

            return [
                'event'         => $event,
                'total_gross'   => $totalGross,
                'total_income'  => $totalIncome,
                'total_expenses'=> $totalExpenses,
                'net_profit'    => $netProfit,
                'total_entries' => $totalEntries,
            ];
        });

        $financeSummary = [
            'total_gross'    => $financeByEvent->sum('total_gross'),
            'total_income'   => $financeByEvent->sum('total_income'),
            'total_expenses' => $financeByEvent->sum('total_expenses'),
            'net_profit'     => $financeByEvent->sum('net_profit'),
            'total_entries'  => $financeByEvent->sum('total_entries'),
        ];

        return view('backend.superadmin.index', compact(
            'totalUsers',
            'totalPlayers',
            'totalEvents',
            'activeEvents',
            'totalRegistrations',
            'recentRegistrations',
            'newUsersThisWeek',
            'newUsersThisMonth',
            'newPlayersThisWeek',
            'pendingWithdrawals',
            'agreementStats',
            'profileStats',
            'playersNeedingAttention',
            'agreements',
            'recentUsers',
            'activityLogs',
            'activityByUser',
            'logNames',
            'loginAuditLogs',
            'loginAuditTodayCount',
            'loginAuditFailedToday',
            'financeByEvent',
            'financeSummary'
        ));
    }
}
