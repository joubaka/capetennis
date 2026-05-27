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
use App\Models\Withdrawals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Spatie\Activitylog\Models\Activity;

class SuperAdminController extends Controller
{
    public function __construct(private FinancialLedgerService $ledger) {}

    /**
     * Show the consolidated Super Admin Dashboard.
     */
    public function index(Request $request)
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

        // ── Financial Dashboard (all events) — uses canonical FinancialLedgerService ──
        $allEvents = Event::with(['incomeItems'])->orderByDesc('start_date')->get();

        $financeYears = $allEvents
            ->filter(fn ($e) => $e->start_date)
            ->map(fn ($e) => (string) \Carbon\Carbon::parse($e->start_date)->year)
            ->unique()
            ->sort()
            ->values();

        $financeYear = $request->input('finance_year');
        if (! $financeYears->contains($financeYear)) {
            $financeYear = $financeYears->last() ?? (string) now()->year;
        }

        $eventsForYear = $allEvents->filter(
            fn ($e) => $e->start_date && (string) \Carbon\Carbon::parse($e->start_date)->year === $financeYear
        );

        $financeByEvent = $eventsForYear->map(
            fn ($event) => $this->ledger->buildFySummaryRow($event)
        );

        $financeSummary = [
            'total_gross'    => round($financeByEvent->sum('total_gross'), 2),
            'total_income'   => round($financeByEvent->sum('total_income'), 2),
            'total_entries'  => $financeByEvent->sum('total_entries'),
            'total_paid_out' => round($financeByEvent->sum('total_paid_out'), 2),
            'balance'        => round($financeByEvent->sum('balance'), 2),
        ];

        // ── Settings variables (for the embedded Settings tab) ──────────────
        $payfastSettings      = SiteSetting::where('group', SiteSetting::GROUP_PAYFAST)->get()->keyBy('key');
        $paymentMethods       = SiteSetting::PAYMENT_METHOD_LABELS;
        $generalSettings      = SiteSetting::where('group', SiteSetting::GROUP_GENERAL)->get()->pluck('value', 'key')->toArray();
        $emailSettings        = SiteSetting::where('group', SiteSetting::GROUP_EMAIL)->get()->pluck('value', 'key')->toArray();
        $registrationSettings = SiteSetting::where('group', SiteSetting::GROUP_REGISTRATION)->get()->pluck('value', 'key')->toArray();

        // ── Wallets (for the Wallets tab) ─────────────────────────────────────
        $wallets = Wallet::with(['payable', 'transactions'])
            ->where('payable_type', 'like', '%User%')
            ->get()
            ->sortByDesc('balance')
            ->values();

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
            ->get();

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
            'withdrawalPendingRefunds',
            'withdrawalCompletedRefunds',
            'withdrawalWalletRefunds',
            'withdrawalPendingTeamRefunds',
            'withdrawalCompletedTeamRefunds',
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
            'financeYear',
            'financeYears',
            'payfastSettings',
            'paymentMethods',
            'generalSettings',
            'emailSettings',
            'registrationSettings',
            'wallets',
            'disciplinaryStats',
            'recentViolations',
            'activeSuspensions'
        ));
    }

    /**
     * PayFast signature debug tool — shows field-by-field breakdown for comparison.
     */
    public function payfastSignatureCheck()
    {
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

        return response()->json([
            'step1_mode'              => $isSandbox ? 'sandbox' : 'live',
            'step2_merchant_id'       => $payfast->id,
            'step3_passphrase_set'    => !empty($passphrase),
            'step3_passphrase_len'    => strlen($passphrase ?? ''),
            'step4_sorted_field_keys' => array_keys($data),
            'step5_field_breakdown'   => $fieldBreakdown,
            'step6_full_string'       => $pfOutput,
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
