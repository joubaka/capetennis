<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\Agreement;
use App\Models\CategoryEventRegistration;
use App\Models\DisciplineSetting;
use App\Models\Event;
use App\Models\Player;
use App\Models\PlayerAgreement;
use App\Models\PlayerSuspension;
use App\Models\PlayerViolation;
use App\Models\Registration;
use App\Models\SiteSetting;
use App\Models\TeamPaymentOrder;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Spatie\Activitylog\Models\Activity;

class SuperAdminController extends Controller
{
    public function __construct(private FinancialLedgerService $ledger) {}

    public function mailHistory(Request $request)
    {
        return view('backend.superadmin.mail-history', app(\App\Services\SuperAdminMailHistory::class)->data($request));
    }

    /**
     * Show the lightweight Super Admin landing page.
     */
    public function index(Request $request)
    {
        $today = Carbon::today();
        $currentYear = (int) $today->year;

        $activeEvents = Event::where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->count();

        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $monthStart->copy()->subMonth();
        // Count each registration once, even when it has multiple paid category entries.
        $paidRegistrations = Registration::whereHas('categoryEventRegistrations',
            fn ($query) => $query->where('payment_status_id', 1));
        $paidRegistrationsThisYear = (clone $paidRegistrations)
            ->where('created_at', '>=', $now->copy()->startOfYear())
            ->where('created_at', '<=', $now)
            ->count();
        $paidRegistrationsLastMonth = (clone $paidRegistrations)
            ->where('created_at', '>=', $lastMonthStart)
            ->where('created_at', '<', $monthStart)
            ->count();
        $lastMonthLabel = $lastMonthStart->format('F Y');
        $newUsersThisWeek = User::where('created_at', '>=', Carbon::now()->startOfWeek())->count();

        $pendingIndividualRefunds = CategoryEventRegistration::where('status', 'withdrawn')
            ->where('refund_method', 'bank')
            ->where('refund_status', 'pending')
            ->count();
        $pendingTeamRefunds = TeamPaymentOrder::where('refund_method', 'bank')
            ->where('refund_status', 'pending')
            ->count();
        $pendingRefunds = $pendingIndividualRefunds + $pendingTeamRefunds;

        $activeSuspensions = PlayerSuspension::whereNull('lifted_at')
            ->where('ends_at', '>', $today->toDateString())
            ->count();

        $oneYearAgo = Carbon::now()->subYear();
        $playersNeedingAttention = Player::where(function ($query) use ($oneYearAgo) {
            $query->whereNull('profile_updated_at')
                ->orWhere('profile_updated_at', '<', $oneYearAgo)
                ->orWhere('profile_complete', false)
                ->orWhereNull('profile_complete');
        })->count();

        $recentActivity = Activity::with('causer')->latest()->limit(5)->get();

        return view('backend.superadmin.home', compact(
            'activeEvents',
            'paidRegistrationsThisYear',
            'paidRegistrationsLastMonth',
            'lastMonthLabel',
            'newUsersThisWeek',
            'pendingRefunds',
            'activeSuspensions',
            'playersNeedingAttention',
            'currentYear',
            'recentActivity',
        ));
    }

    /** Load ledger work separately so navigation and attention queues render first. */
    public function balance()
    {
        $year = (int) Carbon::today()->year;
        $balance = 0;

        Event::with('incomeItems')
            ->where('start_date', '>=', Carbon::create($year, 1, 1)->toDateString())
            ->where('start_date', '<', Carbon::create($year + 1, 1, 1)->toDateString())
            ->chunkById(25, function ($events) use (&$balance) {
                foreach ($events as $event) {
                    $balance += $this->ledger->buildFySummaryRow($event)['balance'];
                }
            });

        return response()->json([
            'year' => $year,
            'balance' => number_format(round($balance, 2), 2, '.', ''),
            'formatted' => 'R '.number_format(round($balance, 2), 2),
        ])->header('Cache-Control', 'no-store, private');
    }

    /**
     * Show the legacy consolidated workspace while its remaining specialist
     * tools are moved to dedicated pages.
     */
    public function workspace(Request $request)
    {
        $tabs = ['overview', 'finance', 'withdrawals', 'agreements', 'players', 'audit', 'mails', 'settings', 'wallets', 'disciplinary'];
        $requestedTab = $request->input('tab', 'overview');
        $selectedTab = is_string($requestedTab) && in_array($requestedTab, $tabs, true) ? $requestedTab : 'overview';
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

        // ── Agreement statistics ─────────────────────────────────────────────
        $activeAgreement = Agreement::select(['id', 'title', 'version'])->where('is_active', 1)->latest()->first();
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


        $pendingIndividualQuery = CategoryEventRegistration::where('status', 'withdrawn')->where('refund_method', 'bank')->where('refund_status', 'pending');
        $pendingTeamQuery = TeamPaymentOrder::where('refund_method', 'bank')->where('refund_status', 'pending');
        $pendingWithdrawals = (clone $pendingIndividualQuery)->count() + (clone $pendingTeamQuery)->count();
        $attentionQuery = Player::where(function ($q) use ($oneYearAgo) {
            $q->whereNull('profile_updated_at')->orWhere('profile_updated_at', '<', $oneYearAgo)
                ->orWhere('profile_complete', false)->orWhereNull('profile_complete');
        });
        $playersAttentionCount = (clone $attentionQuery)->count();
        $activeSuspensionCount = PlayerSuspension::whereNull('lifted_at')->where('ends_at', '>', Carbon::today()->toDateString())->count();
        $data = compact('selectedTab', 'totalUsers', 'totalPlayers', 'totalEvents', 'activeEvents', 'totalRegistrations',
            'recentRegistrations', 'newUsersThisWeek', 'newUsersThisMonth', 'newPlayersThisWeek', 'pendingWithdrawals',
            'agreementStats', 'playersAttentionCount', 'activeSuspensionCount');

        if ($selectedTab === 'overview') {
            // ── Player Profile Status ─────────────────────────────────────────────
            $profileStats = [
                'up_to_date'    => Player::where('profile_complete', true)
                                         ->where('profile_updated_at', '>=', $oneYearAgo)
                                         ->count(),
                'needs_update'  => Player::where('profile_complete', true)->whereNotNull('profile_updated_at')
                                         ->where('profile_updated_at', '<', $oneYearAgo)->count(),
                'incomplete'    => Player::where(function ($q) {
                                       $q->where('profile_complete', false)
                                         ->orWhereNull('profile_complete');
                                   })->count(),
                'never_updated' => Player::where('profile_complete', true)->whereNull('profile_updated_at')->count(),
            ];


            $recentUsers = User::orderByDesc('created_at')->orderByDesc('id')->limit(10)->get();
            $data += compact('profileStats', 'recentUsers');
        }
        if ($selectedTab === 'players') {
            $playersNeedingAttention = $attentionQuery->with('user')->orderBy('profile_updated_at')->orderBy('id')
                ->paginate(25, ['*'], 'player_page')->appends(['tab' => 'players']);
            $data += compact('playersNeedingAttention');
        }
        if ($selectedTab === 'agreements') {
            $agreements = Agreement::withCount('playerAgreements')->orderByDesc('created_at')->orderByDesc('id')
                ->paginate(25, ['*'], 'agreement_page')->appends(['tab' => 'agreements']);
            $data += compact('agreements');
        }
        if ($selectedTab === 'withdrawals') {
            $requestedWithdrawalTab = $request->input('withdrawal_tab', 'pending');
            $withdrawalTab = is_string($requestedWithdrawalTab) && in_array($requestedWithdrawalTab, ['pending', 'completed', 'wallet'], true)
                ? $requestedWithdrawalTab : 'pending';
            $completedIndividualQuery = CategoryEventRegistration::where('status', 'withdrawn')->where('refund_method', 'bank')->where('refund_status', 'completed');
            $completedTeamQuery = TeamPaymentOrder::where('refund_method', 'bank')->where('refund_status', 'completed');
            $walletRefundQuery = CategoryEventRegistration::where('status', 'withdrawn')->where(function ($q) {
                $q->where('refund_method', 'wallet')->orWhereNull('refund_method');
            });
            $completedRefundCount = (clone $completedIndividualQuery)->count() + (clone $completedTeamQuery)->count();
            $walletRefundCount = (clone $walletRefundQuery)->count();
            $context = ['tab' => 'withdrawals', 'withdrawal_tab' => $withdrawalTab];
            $relations = ['categoryEvent.event', 'players', 'registration', 'user'];
            $data += compact('withdrawalTab', 'completedRefundCount', 'walletRefundCount');
            if ($withdrawalTab === 'wallet') {
                $withdrawalWalletRefunds = $walletRefundQuery->with($relations)->orderByDesc('withdrawn_at')->orderByDesc('id')
                    ->paginate(25, ['*'], 'wallet_refund_page')->appends($context);
                $data += compact('withdrawalWalletRefunds');
            } else {
                $individual = $withdrawalTab === 'pending' ? $pendingIndividualQuery : $completedIndividualQuery;
                $team = $withdrawalTab === 'pending' ? $pendingTeamQuery : $completedTeamQuery;
                $dateColumn = $withdrawalTab === 'pending' ? 'updated_at' : 'refunded_at';
                $direction = $withdrawalTab === 'pending' ? 'asc' : 'desc';
                $individualRefunds = $individual->with($relations)->orderBy($dateColumn, $direction)->orderBy('id', $direction)
                    ->paginate(25, ['*'], 'refund_page')->appends($context + ['team_refund_page' => max(1, $request->integer('team_refund_page', 1))]);
                $teamRefunds = $team->with(['team', 'player', 'user', 'event'])->orderBy($dateColumn, $direction)->orderBy('id', $direction)
                    ->paginate(25, ['*'], 'team_refund_page')->appends($context + ['refund_page' => max(1, $request->integer('refund_page', 1))]);
                $data += $withdrawalTab === 'pending'
                    ? ['withdrawalPendingRefunds' => $individualRefunds, 'withdrawalPendingTeamRefunds' => $teamRefunds]
                    : ['withdrawalCompletedRefunds' => $individualRefunds, 'withdrawalCompletedTeamRefunds' => $teamRefunds];
            }
        }
        if ($selectedTab === 'audit') {
            // ── Activity Log (Spatie) ─────────────────────────────────────────────
            $activityLogs = Activity::with('causer')->latest()->limit(100)->get();

            $activityByUser = collect();
            if ($activityLogs->isNotEmpty()) {
                $activityByUser = $activityLogs
                    ->groupBy(fn ($a) => $a->causer_id ?? 'system')
                    ->map(function ($group) {
                        $first  = $group->first();
                        $latest = $group->sortByDesc('created_at')->first();
                        return (object) [
                            'causer'              => $first->causer,
                            'causer_id'           => $first->causer_id,
                            'count'               => $group->count(),
                            'last_at'             => $latest->created_at,
                            'example_description' => $latest->description,
                            'log_names'           => $group->pluck('log_name')->unique()->values()->toArray(),
                            'last_log_name'       => $latest->log_name,
                            'last_properties'     => $latest->properties,
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


            $data += compact('activityLogs', 'activityByUser', 'logNames', 'loginAuditLogs', 'loginAuditTodayCount', 'loginAuditFailedToday');
        }
        if ($selectedTab === 'finance') {
            $yearExpression = DB::getDriverName() === 'sqlite' ? "strftime('%Y', start_date)" : 'YEAR(start_date)';
            $financeYears = Event::whereNotNull('start_date')->selectRaw("{$yearExpression} as finance_year")
                ->distinct()->orderBy('finance_year')->pluck('finance_year')->map(fn ($year) => (string) $year)->values();
            $requestedYear = $request->input('finance_year');
            $financeYear = is_string($requestedYear) && $financeYears->contains($requestedYear)
                ? $requestedYear : ($financeYears->last() ?? (string) now()->year);
            $yearQuery = Event::whereYear('start_date', (int) $financeYear);
            $financeEvents = (clone $yearQuery)->orderByDesc('start_date')->orderByDesc('id')
                ->paginate(25, ['*'], 'finance_page')->appends(['tab' => 'finance', 'finance_year' => $financeYear]);
            $pageIds = $financeEvents->pluck('id')->flip();
            $pageRows = [];
            $financeSummary = ['total_gross' => 0, 'total_income' => 0, 'total_entries' => 0, 'total_paid_out' => 0, 'balance' => 0];
            $yearQuery->with('incomeItems')->chunkById(25, function ($events) use (&$financeSummary, &$pageRows, $pageIds) {
                foreach ($events as $event) {
                    $row = $this->ledger->buildFySummaryRow($event);
                    foreach (['total_gross', 'total_income', 'total_entries', 'balance'] as $key) {
                        $financeSummary[$key] += $row[$key];
                    }
                    $financeSummary['total_paid_out'] += $row['combined_paid_out'];
                    if ($pageIds->has($event->id)) $pageRows[$event->id] = $row;
                }
            });
            foreach (['total_gross', 'total_income', 'total_paid_out', 'balance'] as $key) {
                $financeSummary[$key] = round($financeSummary[$key], 2);
            }
            $financeByEvent = $financeEvents->getCollection()->map(fn ($event) => $pageRows[$event->id]);
            $data += compact('financeYear', 'financeYears', 'financeSummary', 'financeByEvent', 'financeEvents');
        }
        if ($selectedTab === 'settings') {
            // ── Settings variables (for the embedded Settings tab) ──────────────
            $payfastSettings      = SiteSetting::where('group', SiteSetting::GROUP_PAYFAST)->get()->keyBy('key');
            $paymentMethods       = SiteSetting::PAYMENT_METHOD_LABELS;
            $generalSettings      = SiteSetting::where('group', SiteSetting::GROUP_GENERAL)->get()->pluck('value', 'key')->toArray();
            $emailSettings        = SiteSetting::where('group', SiteSetting::GROUP_EMAIL)->get()->pluck('value', 'key')->toArray();
            $registrationSettings = SiteSetting::where('group', SiteSetting::GROUP_REGISTRATION)->get()->pluck('value', 'key')->toArray();


            $data += compact('payfastSettings', 'paymentMethods', 'generalSettings', 'emailSettings', 'registrationSettings');
        }
        if ($selectedTab === 'wallets') {
            // ── Wallets (for the Wallets tab) ─────────────────────────────────────
            $request->validate(['wallet_search' => ['nullable', 'string', 'max:255']]);
            $walletSearch = trim((string) $request->input('wallet_search', ''));

            $wallets = Wallet::query()
                ->with('payable')
                ->select('wallets.*')
                ->withCount('transactions')
                ->selectSub(function ($query) {
                    $query->from('wallet_transactions')
                        ->selectRaw("COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE -amount END), 0)")
                        ->whereColumn('wallet_transactions.wallet_id', 'wallets.id');
                }, 'computed_balance')
                ->where('payable_type', 'like', '%User%')
                ->when($walletSearch !== '', function ($query) use ($walletSearch) {
                    $query->whereHasMorph('payable', [User::class], function ($userQuery) use ($walletSearch) {
                        $userQuery->where(function ($matchingUser) use ($walletSearch) {
                            $matchingUser->where('name', 'like', "%{$walletSearch}%")
                                ->orWhere('email', 'like', "%{$walletSearch}%");
                        });
                    });
                })
                ->orderByDesc('computed_balance')
                ->orderBy('wallets.id')
                ->paginate(25, ['*'], 'wallet_page')
                ->appends(['tab' => 'wallets', 'wallet_search' => $walletSearch]);


            $data += compact('wallets', 'walletSearch');
        }
        if ($selectedTab === 'disciplinary') {
            // ── Disciplinary stats ─────────────────────────────────────────────
            $expiryDays = DisciplineSetting::expiryDays();
            $expiryDate = Carbon::now()->subDays($expiryDays)->toDateString();

            $disciplinaryStats = [
                'total_violations'        => PlayerViolation::count(),
                'active_violations'       => PlayerViolation::where('violation_date', '>=', $expiryDate)->count(),
                'active_suspensions'      => PlayerSuspension::whereNull('lifted_at')
                                                ->where('ends_at', '>', Carbon::today()->toDateString())
                                                ->count(),
                'total_suspensions'       => PlayerSuspension::count(),
                'players_with_violations' => PlayerViolation::where('violation_date', '>=', $expiryDate)
                                                ->distinct('player_id')
                                                ->count('player_id'),
                'threshold'               => DisciplineSetting::suspensionThreshold(),
            ];

            $recentViolations = PlayerViolation::with(['player', 'violationType'])
                ->orderByDesc('violation_date')
                ->limit(10)
                ->get();

            $activeSuspensions = PlayerSuspension::with('player')
                ->whereNull('lifted_at')
                ->where('ends_at', '>', Carbon::today()->toDateString())
                ->orderBy('ends_at')
                ->orderBy('id')->paginate(25, ['*'], 'suspension_page')->appends(['tab' => 'disciplinary']);


            $data += compact('disciplinaryStats', 'recentViolations', 'activeSuspensions');
        }
        if ($selectedTab === 'mails') {
            $data += app(\App\Services\SuperAdminMailHistory::class)->data($request);
        }
        return view('backend.superadmin.index', $data);
    }

    /**
     * PayFast signature debug tool — shows field-by-field breakdown for comparison.
     */
    public function payfastSignatureCheck()
    {
        abort_if(app()->environment('production'), 404);

        $payfast = new \App\Services\Payfast();

        $isSandbox = config('services.payfast.sandbox', false);
        if ($isSandbox) {
            $payfast->setMode(0);
        }

        $passphrase = $isSandbox
            ? (config('services.payfast.passphrase_sandbox') ?: config('services.payfast.passphrase'))
            : (config('services.payfast.passphrase_live')    ?: config('services.payfast.passphrase'));

        // Use exact same fields a real checkout would send
        $rawFields = [
            'merchant_id'  => $payfast->id,
            'merchant_key' => $payfast->key,
            'return_url'   => $payfast->return_url,
            'cancel_url'   => $payfast->cancel_url,
            'notify_url'   => $payfast->notify_url,
            'amount'       => '100.00',
            'item_name'    => 'Test Registration',
        ];

        // Remove empty values (no passphrase in here)
        $data = array_filter($rawFields, fn($v) => $v !== null && $v !== '');
        ksort($data);

        // Build string manually — each field shown individually for debugging
        $fieldBreakdown = [];
        $pfOutput = '';
        foreach ($data as $key => $val) {
            $encoded = urlencode(trim((string)$val));
            $fieldBreakdown[] = [
                'key'           => $key,
                'raw_value'     => $val,
                'encoded_value' => $encoded,
                'pair'          => $key . '=' . $encoded,
            ];
            $pfOutput .= $key . '=' . $encoded . '&';
        }
        $pfOutput = rtrim($pfOutput, '&');

        // Append passphrase last
        $passphraseEncoded = '';
        if (!empty($passphrase)) {
            $passphraseEncoded = urlencode(trim($passphrase));
            $pfOutput .= '&passphrase=' . $passphraseEncoded;
            $fieldBreakdown[] = [
                'key'           => 'passphrase',
                'raw_value'     => str_repeat('*', strlen($passphrase)) . ' (' . strlen($passphrase) . ' chars)',
                'encoded_value' => $passphraseEncoded,
                'pair'          => 'passphrase=' . $passphraseEncoded,
                'note'          => 'APPENDED LAST — not sorted',
            ];
        }

        $signature = md5($pfOutput);

        $safeBreakdown = collect($fieldBreakdown)->map(function (array $field) {
            if (in_array($field['key'], ['merchant_key', 'passphrase'], true)) {
                $field['raw_value'] = '[REDACTED]';
                $field['encoded_value'] = '[REDACTED]';
                $field['pair'] = $field['key'] . '=[REDACTED]';
            }
            return $field;
        })->values();
        $safeOutput = preg_replace('/(merchant_key|passphrase)=[^&]*/', '$1=[REDACTED]', $pfOutput);

        return response()->json([
            'step1_mode'              => $isSandbox ? 'sandbox' : 'live',
            'step2_merchant_id'       => $payfast->id,
            'step3_passphrase_set'    => !empty($passphrase),
            'step3_passphrase_len'    => strlen($passphrase ?? ''),
            'step4_sorted_field_keys' => array_keys($data),
            'step5_field_breakdown'   => $safeBreakdown,
            'step6_full_string'       => $safeOutput,
            'security_note'           => 'Secrets are redacted. Never paste live credentials into third-party tools.',
            'step7_signature_md5'     => $signature,
            'HOW_TO_VERIFY'           => [
                '1_copy_string'  => 'Copy step6_full_string exactly',
                '2_md5_it'       => 'Paste into https://www.md5hashgenerator.com — result must match step7_signature_md5',
                '3_payfast_tool' => 'Or use https://developers.payfast.co.za/api — Signature Generator section',
                '4_compare'      => 'If PayFast generates a different MD5, check step5_field_breakdown for encoding differences',
            ],
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
