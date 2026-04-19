<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use App\Models\TeamPaymentOrder;
use App\Models\Wallet;
use App\Services\MailAccountManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class SystemCheckController extends Controller
{
    public function index()
    {
        $checks = [];

        // 1. Database
        try {
            DB::select('SELECT 1');
            $checks[] = ['name' => 'Database', 'status' => 'ok', 'detail' => 'Connection is live.'];
        } catch (\Throwable $e) {
            $checks[] = ['name' => 'Database', 'status' => 'fail', 'detail' => 'Connection failed: ' . $e->getMessage()];
        }

        // 2. Cache
        try {
            $key   = '_system_check_' . time();
            $value = 'ping_' . rand(1000, 9999);
            Cache::put($key, $value, 10);
            $read  = Cache::get($key);
            Cache::forget($key);

            if ($read === $value) {
                $checks[] = ['name' => 'Cache', 'status' => 'ok', 'detail' => 'Driver: ' . config('cache.default') . '. Write/read verified.'];
            } else {
                $checks[] = ['name' => 'Cache', 'status' => 'fail', 'detail' => 'Cache write/read mismatch. Driver: ' . config('cache.default')];
            }
        } catch (\Throwable $e) {
            $checks[] = ['name' => 'Cache', 'status' => 'fail', 'detail' => 'Cache error: ' . $e->getMessage()];
        }

        // 3. Queue driver
        try {
            $connection = config('queue.default');
            $isProduction = app()->environment('production');

            if ($connection === 'sync' && $isProduction) {
                $checks[] = ['name' => 'Queue', 'status' => 'warning', 'detail' => "Driver is 'sync' in production — jobs run inline and are not queued asynchronously."];
            } else {
                $checks[] = ['name' => 'Queue', 'status' => 'ok', 'detail' => "Driver: {$connection}. Environment: " . app()->environment()];
            }
        } catch (\Throwable $e) {
            $checks[] = ['name' => 'Queue', 'status' => 'fail', 'detail' => 'Queue config error: ' . $e->getMessage()];
        }

        // 4. Mail accounts
        try {
            $manager = new MailAccountManager();
            $status  = $manager->getStatus();
            $limit   = 500;
            $issues  = [];

            foreach ($status as $account => $count) {
                if ($count >= $limit) {
                    $issues[] = "{$account}: {$count}/{$limit} ❌ EXHAUSTED";
                } elseif ($count >= $limit * 0.8) {
                    $issues[] = "{$account}: {$count}/{$limit} ⚠️ near limit";
                } else {
                    $issues[] = "{$account}: {$count}/{$limit}";
                }
            }

            $exhausted = collect($status)->filter(fn($c) => $c >= $limit)->count();
            $nearLimit = collect($status)->filter(fn($c) => $c >= $limit * 0.8 && $c < $limit)->count();

            $detail = implode(', ', $issues);

            if ($exhausted > 0) {
                $checks[] = ['name' => 'Mail Accounts', 'status' => 'fail', 'detail' => $detail];
            } elseif ($nearLimit > 0) {
                $checks[] = ['name' => 'Mail Accounts', 'status' => 'warning', 'detail' => $detail];
            } else {
                $checks[] = ['name' => 'Mail Accounts', 'status' => 'ok', 'detail' => $detail];
            }
        } catch (\Throwable $e) {
            $checks[] = ['name' => 'Mail Accounts', 'status' => 'fail', 'detail' => 'Error reading mail status: ' . $e->getMessage()];
        }

        // 5. PayFast config
        try {
            $merchantId  = config('services.payfast.merchant_id') ?? config('services.payfast.merchant-id');
            $merchantKey = config('services.payfast.merchant_key') ?? config('services.payfast.merchant-key');
            $passphrase  = config('services.payfast.passphrase');

            $missing = [];
            if (empty($merchantId))  $missing[] = 'PAYFAST_MERCHANT_ID';
            if (empty($merchantKey)) $missing[] = 'PAYFAST_MERCHANT_KEY';
            if (empty($passphrase))  $missing[] = 'PAYFAST_PASSPHRASE';

            if (empty($missing)) {
                $checks[] = ['name' => 'PayFast Config', 'status' => 'ok', 'detail' => 'All required PayFast keys are set.'];
            } else {
                $checks[] = ['name' => 'PayFast Config', 'status' => 'fail', 'detail' => 'Missing env vars: ' . implode(', ', $missing)];
            }
        } catch (\Throwable $e) {
            $checks[] = ['name' => 'PayFast Config', 'status' => 'fail', 'detail' => 'Config error: ' . $e->getMessage()];
        }

        // 6. Wallet integrity (negative balances)
        try {
            $negativeCount = Wallet::with('transactions')->get()->filter(fn($w) => $w->balance < 0)->count();

            if ($negativeCount === 0) {
                $checks[] = ['name' => 'Wallet Integrity', 'status' => 'ok', 'detail' => 'No wallets with a negative balance.'];
            } else {
                $checks[] = ['name' => 'Wallet Integrity', 'status' => 'fail', 'detail' => "{$negativeCount} wallet(s) have a negative balance — possible over-debit."];
            }
        } catch (\Throwable $e) {
            $checks[] = ['name' => 'Wallet Integrity', 'status' => 'fail', 'detail' => 'Error querying wallets: ' . $e->getMessage()];
        }

        // 7. Refund consistency (withdrawn but no refund_status)
        try {
            $count = CategoryEventRegistration::where('status', 'withdrawn')
                ->where(function ($q) {
                    $q->whereNull('refund_status')->orWhere('refund_status', '');
                })
                ->count();

            if ($count === 0) {
                $checks[] = ['name' => 'Refund Consistency', 'status' => 'ok', 'detail' => 'All withdrawn registrations have a refund_status set.'];
            } else {
                $checks[] = ['name' => 'Refund Consistency', 'status' => 'warning', 'detail' => "{$count} withdrawn registration(s) have no refund_status set (expected 'not_refunded')."];
            }
        } catch (\Throwable $e) {
            $checks[] = ['name' => 'Refund Consistency', 'status' => 'fail', 'detail' => 'Error querying registrations: ' . $e->getMessage()];
        }

        // 8. Pending bank refunds
        try {
            $pendingIndividual = CategoryEventRegistration::where('refund_method', 'bank')
                ->where('refund_status', 'pending')
                ->count();

            $pendingTeam = TeamPaymentOrder::where('refund_method', 'bank')
                ->where('refund_status', 'pending')
                ->count();

            $total = $pendingIndividual + $pendingTeam;

            if ($total === 0) {
                $checks[] = ['name' => 'Pending Bank Refunds', 'status' => 'ok', 'detail' => 'No pending bank refunds.'];
            } else {
                $detail = "⚠️ {$total} pending bank refund(s): {$pendingIndividual} individual, {$pendingTeam} team.";
                $checks[] = ['name' => 'Pending Bank Refunds', 'status' => 'warning', 'detail' => $detail];
            }
        } catch (\Throwable $e) {
            $checks[] = ['name' => 'Pending Bank Refunds', 'status' => 'fail', 'detail' => 'Error querying refunds: ' . $e->getMessage()];
        }

        // 9. Activity log (last 24 h)
        try {
            $count = Activity::where('created_at', '>=', now()->subDay())->count();

            if ($count > 0) {
                $checks[] = ['name' => 'Activity Log', 'status' => 'ok', 'detail' => "{$count} log entries in the last 24 hours. Spatie activity logging is working."];
            } else {
                $checks[] = ['name' => 'Activity Log', 'status' => 'warning', 'detail' => 'No activity log entries in the last 24 hours. Logging may be misconfigured.'];
            }
        } catch (\Throwable $e) {
            $checks[] = ['name' => 'Activity Log', 'status' => 'fail', 'detail' => 'Error querying activity log: ' . $e->getMessage()];
        }

        // 10. Environment
        try {
            $env   = app()->environment();
            $debug = config('app.debug');
            $url   = config('app.url');

            $status = 'ok';
            $detail = "APP_ENV: {$env} | APP_DEBUG: " . ($debug ? 'true' : 'false') . " | APP_URL: {$url}";

            if ($debug && $env === 'production') {
                $status = 'warning';
                $detail .= ' ⚠️ APP_DEBUG should be false in production.';
            }

            $checks[] = ['name' => 'Environment', 'status' => $status, 'detail' => $detail];
        } catch (\Throwable $e) {
            $checks[] = ['name' => 'Environment', 'status' => 'fail', 'detail' => 'Error reading environment config: ' . $e->getMessage()];
        }

        $failCount    = collect($checks)->where('status', 'fail')->count();
        $warningCount = collect($checks)->where('status', 'warning')->count();

        return view('backend.system.check', compact('checks', 'failCount', 'warningCount'));
    }
}
