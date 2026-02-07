<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Controllers\Frontend\RegisterController;
use App\Models\Transaction;
use Throwable;

class ImportPayfastTransactions extends Command
{
  protected $signature = 'payfast:import
        {file : Path to CSV or XLSX file}
        {--dry-run : Validate only, no DB writes}';

  protected $description = 'Import PayFast transactions and replay them through existing RegisterController PayFast logic';

  public function handle(): int
  {
    $file = $this->argument('file');
    $dryRun = (bool) $this->option('dry-run');

    if (!file_exists($file)) {
      $this->error("File not found: {$file}");
      return self::FAILURE;
    }

    $this->info('Reading file...');
    $rows = Excel::toArray([], $file)[0] ?? [];

    if (count($rows) < 2) {
      $this->error('No data rows found');
      return self::FAILURE;
    }

    // Header row
    $headers = array_map('trim', $rows[0]);
    unset($rows[0]);

    $imported = 0;
    $skipped = 0;
    $failed = 0;

    // Instantiate controller directly
    $registerController = app(RegisterController::class);

    DB::beginTransaction();

    try {
      foreach ($rows as $index => $row) {
        $row = array_combine($headers, $row);

        /**
         * ------------------------------------
         * FILTER: ONLY CUSTOMER PAYMENTS
         * ------------------------------------
         */
        if (
          ($row['Type'] ?? null) !== 'Funds Received' ||
          ($row['Sign'] ?? null) !== 'Credit' ||
          empty($row['PF Payment ID'])
        ) {
          continue;
        }

        /**
         * ------------------------------------
         * IDEMPOTENCY
         * ------------------------------------
         */
        if (
          Transaction::where('pf_payment_id', $row['PF Payment ID'])->exists()
        ) {
          $skipped++;
          continue;
        }

        /**
         * ------------------------------------
         * BUILD PAYFAST-STYLE PAYLOAD
         * ------------------------------------
         */
        $payfastData = [
          'pf_payment_id' => (string) $row['PF Payment ID'],

          'amount_gross' => (float) $row['Gross'],
          'amount_fee' => abs((float) $row['Fee']),
          'amount_net' => (float) $row['Net'],

          'item_name' => $row['Description'] ?? null,
          'email_address' => $row['Email / Cell'] ?? null,

          'custom_int3' => (int) ($row['Custom_int3'] ?? null), // event_id
          'custom_int4' => (int) ($row['Custom_int4'] ?? null), // user_id
          'custom_int5' => (int) ($row['Custom_int5'] ?? null), // registration_order_id

          'custom_str3' => $row['Custom_str3'] ?? null,
          'custom_str4' => $row['Name'] ?? null,
          'custom_str5' => $row['Custom_str5'] ?? null,

          'payment_date' => Carbon::parse($row['Date'])->toDateTimeString(),
          'source' => 'payfast_import',
        ];

        if ($dryRun) {
          $this->line(
            "DRY-RUN OK → PF {$payfastData['pf_payment_id']} | Order {$payfastData['custom_int5']}"
          );
          $imported++;
          continue;
        }

        /**
         * ------------------------------------
         * REPLAY THROUGH EXISTING LOGIC
         * ------------------------------------
         */
        $registerController->updateRegistrationFromPayfast($payfastData);

        $imported++;
      }

      if ($dryRun) {
        DB::rollBack();
        $this->warn('Dry-run complete. No data written.');
      } else {
        DB::commit();
        $this->info('Import committed successfully.');
      }

    } catch (Throwable $e) {
      DB::rollBack();

      Log::error('[PayFast Import] Failed', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);

      $this->error($e->getMessage());
      return self::FAILURE;
    }

    /**
     * ------------------------------------
     * SUMMARY
     * ------------------------------------
     */
    $this->table(
      ['Result', 'Count'],
      [
        ['Imported', $imported],
        ['Skipped (duplicates)', $skipped],
        ['Failed', $failed],
      ]
    );

    return self::SUCCESS;
  }
}
