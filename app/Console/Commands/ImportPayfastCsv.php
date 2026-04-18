<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\Transaction;
use App\Models\RegistrationOrder;
use App\Models\Registration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ImportPayfastCsv extends Command
{
    protected $signature = 'import:transactions {path} {--reconcile : Also reconcile orders/registrations when custom_int5 present}';

    protected $description = 'Import PayFast CSV transactions into the transactions table. Use --reconcile to mark orders/registrations paid when possible.';

    public function handle()
    {
        $path = $this->argument('path');
        $reconcile = $this->option('reconcile');

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

        $head = array_map(function ($h) {
            $h = strtolower(trim($h));
            $h = preg_replace('/[^a-z0-9]+/', '_', $h);
            return trim($h, '_');
        }, $header);

        $idx = array_flip($head);

        // detect columns
        $pfColNames = ['pf_payment_id', 'pf_payment', 'pf_paymentid'];
        $amountColNames = ['gross', 'amount_gross', 'amount'];
        $customIntCols = ['custom_int1','custom_int2','custom_int3','custom_int4','custom_int5'];
        $customStrCols = ['custom_str1','custom_str2','custom_str3','custom_str4','custom_str5'];
        $itemNameCols = ['item_name'];
        $emailCols = ['email','email_address','email_address_'];

        $pfIndex = null;
        foreach ($pfColNames as $c) { if (isset($idx[$c])) { $pfIndex = $idx[$c]; break; } }
        if ($pfIndex === null) {
            $this->error('pf_payment_id column not detected in CSV');
            fclose($handle);
            return 1;
        }

        $amountIndex = null;
        foreach ($amountColNames as $c) { if (isset($idx[$c])) { $amountIndex = $idx[$c]; break; } }

        $customIntIndexMap = [];
        foreach ($customIntCols as $c) { if (isset($idx[$c])) { $customIntIndexMap[$c] = $idx[$c]; } }
        $customStrIndexMap = [];
        foreach ($customStrCols as $c) { if (isset($idx[$c])) { $customStrIndexMap[$c] = $idx[$c]; } }

        $itemNameIndex = isset($idx['item_name']) ? $idx['item_name'] : null;
        $emailIndex = null;
        foreach ($emailCols as $c) { if (isset($idx[$c])) { $emailIndex = $idx[$c]; break; } }

        $total = 0; $imported = 0; $skipped = 0; $reconciled = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $total++;
            $pf = isset($row[$pfIndex]) ? trim($row[$pfIndex]) : '';
            if ($pf === '') { $this->line("#{$total} - empty pf -> skipped"); $skipped++; continue; }

            // normalize pf (string)
            $pf = preg_replace('/[^0-9A-Za-z_-]/', '', $pf);

            // check existing
            $exists = Transaction::where('pf_payment_id', $pf)->first();
            if ($exists) { $this->line("#{$total} pf={$pf} -> already exists (tx_id={$exists->id})"); $skipped++; continue; }

            $amount = null;
            if ($amountIndex !== null && isset($row[$amountIndex])) {
                $raw = $row[$amountIndex];
                $raw = str_replace([',',' ', 'R'], ['', '', ''], $raw);
                $amount = is_numeric($raw) ? (float) $raw : null;
            }

            // build data
            $data = [];
            for ($i=1;$i<=5;$i++) {
                $k = "custom_int{$i}";
                if (isset($customIntIndexMap[$k]) && isset($row[$customIntIndexMap[$k]])) {
                    $data[$k] = trim($row[$customIntIndexMap[$k]]);
                }
                $ks = "custom_str{$i}";
                if (isset($customStrIndexMap[$ks]) && isset($row[$customStrIndexMap[$ks]])) {
                    $data[$ks] = trim($row[$customStrIndexMap[$ks]]);
                }
            }

            if ($itemNameIndex !== null && isset($row[$itemNameIndex])) {
                $data['item_name'] = $row[$itemNameIndex];
            }
            if ($emailIndex !== null && isset($row[$emailIndex])) {
                $data['email_address'] = $row[$emailIndex];
            }

            // create transaction inside DB transaction
            try {
                DB::transaction(function () use ($pf, $amount, $data, $reconcile, &$imported, &$reconciled, $total) {
                    $tx = new Transaction();
                    $tx->transaction_type = 'Registration';
                    $tx->amount_gross = $amount;
                    $tx->amount_fee = null;
                    $tx->amount_net = null;
                    // map custom ints/strs
                    foreach (['1','2','3','4','5'] as $i) {
                        $intKey = "custom_int{$i}";
                        $strKey = "custom_str{$i}";
                        if (!empty($data[$intKey])) $tx->{$intKey} = is_numeric($data[$intKey]) ? (int)$data[$intKey] : $data[$intKey];
                        if (!empty($data[$strKey])) $tx->{$strKey} = $data[$strKey];
                    }

                    if (!empty($data['item_name'])) $tx->item_name = $data['item_name'];
                    if (!empty($data['email_address'])) $tx->email_address = $data['email_address'];

                    $tx->pf_payment_id = $pf;

                    // try to set event/category/player ids from custom ints
                    if (!empty($data['custom_int3'])) $tx->event_id = (int)$data['custom_int3'];
                    if (!empty($data['custom_int1'])) $tx->category_event_id = (int)$data['custom_int1'];
                    if (!empty($data['custom_int2'])) $tx->player_id = (int)$data['custom_int2'];

                    $tx->save();
                    $imported++;
                    $this->info("#{$total} imported pf={$pf} tx_id={$tx->id}");

                    // optional reconcile: if custom_int5 present, mark order and registrations
                    if ($reconcile && !empty($data['custom_int5'])) {
                        $orderId = (int)$data['custom_int5'];
                        $order = RegistrationOrder::with('items')->find($orderId);
                        if ($order) {
                            // mark paid
                            $order->payfast_amount_due = $order->payfast_amount_due > 0 ? $order->payfast_amount_due : ($amount ?? 0);
                            $order->payfast_paid = true;
                            $order->pay_status = 1;
                            $order->payfast_pf_payment_id = $pf;
                            $order->save();

                            // mark registrations
                            foreach ($order->items as $item) {
                                $reg = Registration::find($item->registration_id);
                                if ($reg) {
                                    $reg->categoryEvents()->syncWithoutDetaching([
                                        $item->category_event_id => [
                                            'payment_status_id' => 1,
                                            'user_id' => $order->user_id,
                                            'pf_transaction_id' => $pf,
                                        ]
                                    ]);
                                }
                            }

                            $reconciled++;
                            $this->info("#{$total} reconciled order={$orderId}");
                        } else {
                            $this->warn("#{$total} reconcile: order {$orderId} not found");
                        }
                    }
                });
            } catch (\Throwable $e) {
                Log::error('[IMPORT_PAYFAST] failed to import', ['pf' => $pf, 'error' => $e->getMessage()]);
                $this->error("#{$total} failed pf={$pf} error={$e->getMessage()}");
            }
        }

        fclose($handle);

        $summary = "Imported: {$imported}, skipped: {$skipped}, reconciled: {$reconciled}, total_rows: {$total}";
        $this->info($summary);
        Log::info('[IMPORT_PAYFAST] ' . $summary);

        return 0;
    }
}
