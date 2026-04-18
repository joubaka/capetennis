<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class CheckPayfastCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage: php artisan check:transactions "C:\\path\\to\\file.csv"
     *
     * @var string
     */
    protected $signature = 'check:transactions {path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compare PayFast transaction CSV to the transactions table (by pf_payment_id)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = $this->argument('path');

        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return 1;
        }

        if (($handle = fopen($path, 'r')) === false) {
            $this->error('Unable to open file.');
            return 1;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            $this->error('CSV appears empty');
            fclose($handle);
            return 1;
        }

        // normalize header keys: lowercase, replace non-alphanum with underscore
        $head = array_map(function ($h) {
            $h = strtolower(trim($h));
            $h = preg_replace('/[^a-z0-9]+/', '_', $h);
            $h = trim($h, '_');
            return $h;
        }, $header);

        $idx = array_flip($head);

        // candidate column names
        $pfCandidates = ['pf_payment_id', 'pf_payment', 'payment_id', 'm_payment_id', 'paymentid', 'pf_paymentid'];
        $amountCandidates = ['amount_gross', 'amount', 'amount_gross_r', 'amount_gross_r2'];
        $dateCandidates = ['payment_date', 'date', 'pf_payment_date', 'payment_dt'];

        $pfIndex = null;
        foreach ($pfCandidates as $c) {
            if (isset($idx[$c])) { $pfIndex = $idx[$c]; break; }
        }

        $amountIndex = null;
        foreach ($amountCandidates as $c) {
            if (isset($idx[$c])) { $amountIndex = $idx[$c]; break; }
        }

        $dateIndex = null;
        foreach ($dateCandidates as $c) {
            if (isset($idx[$c])) { $dateIndex = $idx[$c]; break; }
        }

        if ($pfIndex === null) {
            $this->warn('Could not detect a pf_payment_id column. Header keys: ' . implode(', ', $head));
            fclose($handle);
            return 1;
        }

        $total = 0;
        $found = 0;
        $missing = 0;
        $mismatch = 0;

        $reportLines = [];

        while (($row = fgetcsv($handle)) !== false) {
            $total++;
            $pf = isset($row[$pfIndex]) ? trim($row[$pfIndex]) : '';
            if ($pf === '') {
                $reportLines[] = "#{$total} - empty pf_payment_id -> skipped";
                $this->line("#{$total} - empty pf_payment_id -> skipped");
                continue;
            }

            $amount = null;
            if ($amountIndex !== null && isset($row[$amountIndex])) {
                // normalize amount: remove currency and thousands separators
                $raw = $row[$amountIndex];
                $raw = str_replace([',', ' ' , 'R'], ['', '', ''], $raw);
                $amount = is_numeric($raw) ? (float) $raw : null;
            }

            $transaction = Transaction::where('pf_payment_id', $pf)->orWhere('pf_payment_id', 'LIKE', "%{$pf}%")->first();

            if ($transaction) {
                $found++;
                $line = "#{$total} pf={$pf} -> FOUND (tx_id={$transaction->id})";

                if ($amount !== null) {
                    $txAmount = (float) $transaction->amount_gross;
                    // Allow small rounding differences
                    if (round($txAmount, 2) !== round($amount, 2)) {
                        $mismatch++;
                        $line .= " MISMATCH amount csv=" . ($amount === null ? 'null' : $amount) . " db={$txAmount}";
                        $reportLines[] = $line;
                        $this->warn($line);
                        continue;
                    }
                }

                $reportLines[] = $line;
                $this->info($line);
            } else {
                $missing++;
                $line = "#{$total} pf={$pf} -> MISSING in DB";
                $reportLines[] = $line;
                $this->error($line);
            }
        }

        fclose($handle);

        $summary = "Checked: {$total}, found: {$found}, missing: {$missing}, mismatches: {$mismatch}";
        $this->line($summary);
        Log::info('[CHECK_PAYFAST_CSV] ' . $summary);

        // append full report to storage/logs/payfast_csv_check.log
        $logPath = storage_path('logs/payfast_csv_check.log');
        $content = "Report generated at " . now()->toDateTimeString() . "\n" . implode("\n", $reportLines) . "\n" . $summary . "\n\n";
        file_put_contents($logPath, $content, FILE_APPEND | LOCK_EX);

        $this->info('Report appended to ' . $logPath);
        return 0;
    }
}
