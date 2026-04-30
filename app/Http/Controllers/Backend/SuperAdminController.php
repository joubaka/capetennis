<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\Player;
use App\Models\PlayerAgreement;
use App\Models\Registration;
use App\Models\SiteSetting;
use App\Models\TeamPaymentOrder;
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

        // ── Pending withdrawals (legacy Withdrawals model count) ────────────
        $pendingWithdrawals = Withdrawals::count();

        // ── Withdrawal / Refund data for Withdrawals tab ─────────────────────
        $withdrawalPendingRefunds = CategoryEventRegistration::with([
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

        $withdrawalCompletedRefunds = CategoryEventRegistration::with([
                'categoryEvent.event',
                'players',
                'registration',
                'user',
            ])
            ->where('status', 'withdrawn')
            ->where('refund_method', 'bank')
            ->where('refund_status', 'completed')
            ->orderByDesc('refunded_at')
            ->get();

        $withdrawalWalletRefunds = CategoryEventRegistration::with([
                'categoryEvent.event',
                'players',
                'registration',
                'user',
            ])
            ->where('status', 'withdrawn')
            ->where(function ($q) {
                $q->where('refund_method', 'wallet')
                  ->orWhereNull('refund_method');
            })
            ->orderByDesc('withdrawn_at')
            ->get();

        $withdrawalPendingTeamRefunds = TeamPaymentOrder::with(['team', 'player', 'user', 'event'])
            ->where('refund_method', 'bank')
            ->where('refund_status', 'pending')
            ->orderBy('updated_at')
            ->get();

        $withdrawalCompletedTeamRefunds = TeamPaymentOrder::with(['team', 'player', 'user', 'event'])
            ->where('refund_method', 'bank')
            ->where('refund_status', 'completed')
            ->orderByDesc('refunded_at')
            ->get();

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
        // Uses the same logic as EventTransactionController (source of truth):
        //   - Excludes test transactions (is_test = false)
        //   - Recalculates PayFast fee via SiteSetting::calculatePayfastFee()
        //   - Adds wallet amounts to gross (order->wallet_reserved)
        //   - Includes completed refunds as negative ledger entries

        $allEvents = Event::with(['incomeItems'])->orderByDesc('start_date')->get();

        // All real payment transactions, eager-loaded with order items
        $allTransactions = Transaction::with(['order.items'])
            ->where('transaction_type', 'Registration')
            ->where('amount_gross', '>', 0)
            ->where('is_test', false)
            ->get()
            ->groupBy('event_id');

        // All completed refunds across every event (grouped by event_id via categoryEvent)
        $allRefunds = CategoryEventRegistration::with([
                'categoryEvent',
                'payfastTransaction.order.items',
            ])
            ->where('status', 'withdrawn')
            ->where('refund_status', 'completed')
            ->whereHas('payfastTransaction', fn ($q) => $q->where('is_test', false))
            ->get()
            ->groupBy(fn ($r) => $r->categoryEvent->event_id);

        $financeByEvent = $allEvents->map(function ($event) use ($allTransactions, $allRefunds) {
            $feePerEntry = (float) $event->cape_tennis_fee;
            $txForEvent  = $allTransactions->get($event->id, collect());

            // ── Payment ledger rows (mirrors EventTransactionController) ──
            $paymentLedger = $txForEvent->map(function ($tx) use ($feePerEntry) {
                $payfastGross = round((float) $tx->amount_gross, 2);
                $walletUsed   = round((float) optional($tx->order)->wallet_reserved, 2);
                $entryCount   = max(1, $tx->order?->items?->count() ?? 0);
                $pfFee        = SiteSetting::calculatePayfastFee($payfastGross);
                $capeFee      = round($feePerEntry * $entryCount, 2);

                return [
                    'gross'   => $payfastGross + $walletUsed,
                    'fee'     => -$pfFee,
                    'capeFee' => -$capeFee,
                    'net'     => round($payfastGross + $walletUsed - $pfFee - $capeFee, 2),
                    'items'   => $tx->order?->items ?? collect(),
                ];
            });

            // ── Refund ledger rows (mirrors EventTransactionController) ──
            $refundLedger = $allRefunds->get($event->id, collect())
                ->map(function ($reg) use ($feePerEntry) {
                    $payment    = $reg->paymentInfo();
                    if (empty($payment)) {
                        return null;
                    }
                    $grossPaid  = (float) ($payment['gross'] ?? 0);
                    $payfastFee = abs((float) ($payment['fee'] ?? 0));

                    return [
                        'gross'   => -$grossPaid,
                        'fee'     => +$payfastFee,
                        'capeFee' => +$feePerEntry,
                        'net'     => round(-$grossPaid + $payfastFee + $feePerEntry, 2),
                        'items'   => collect(),
                    ];
                })
                ->filter();

            $ledger = $paymentLedger->merge($refundLedger);

            $totalGross   = round($ledger->sum('gross'), 2);
            $netIncome    = round($ledger->sum('net'), 2);

            // Entry count: same as EventTransactionController (items in payment rows only)
            $totalEntries = $event->isTeam()
                ? $txForEvent->count()
                : $paymentLedger->flatMap(fn ($r) => $r['items'])->count();

            return [
                'event'         => $event,
                'total_gross'   => $totalGross,
                'total_income'  => $netIncome,
                'total_entries' => $totalEntries,
            ];
        });

        $financeSummary = [
            'total_gross'   => $financeByEvent->sum('total_gross'),
            'total_income'  => $financeByEvent->sum('total_income'),
            'total_entries' => $financeByEvent->sum('total_entries'),
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
            'financeSummary',
            'withdrawalPendingRefunds',
            'withdrawalCompletedRefunds',
            'withdrawalWalletRefunds',
            'withdrawalPendingTeamRefunds',
            'withdrawalCompletedTeamRefunds'
        ));
    }
}
