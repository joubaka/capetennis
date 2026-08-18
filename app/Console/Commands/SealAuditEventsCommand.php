<?php

namespace App\Console\Commands;

use App\Support\Audit\AuditDailyDigest;
use App\Support\Audit\AuditWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SealAuditEventsCommand extends Command
{
    protected $signature = 'audit:seal {date? : Date in YYYY-MM-DD format; defaults to yesterday}';
    protected $description = 'Create or verify the tamper-evident daily seal for canonical audit events';

    public function handle(AuditDailyDigest $digests, AuditWriter $writer): int
    {
        $date = Carbon::parse($this->argument('date') ?: now()->subDay()->toDateString())->toDateString();
        $digest = $digests->calculate($date);
        $existing = DB::table('audit_daily_seals')->where('audit_date', $date)->first();

        if ($existing) {
            $valid = hash_equals((string) $existing->digest, $digest['digest'])
                && (int) $existing->event_count === $digest['event_count']
                && (int) $existing->first_event_id === (int) $digest['first_event_id']
                && (int) $existing->last_event_id === (int) $digest['last_event_id']
                && $digest['integrity_failures'] === 0;
            $this->line($valid ? "Audit seal verified for {$date}." : "Audit seal FAILED verification for {$date}.");
            return $valid ? self::SUCCESS : self::FAILURE;
        }

        if ($digest['integrity_failures'] > 0) {
            $this->error("Cannot seal {$date}: {$digest['integrity_failures']} event integrity checks failed.");
            return self::FAILURE;
        }

        DB::table('audit_daily_seals')->insert([
            'audit_date' => $date,
            'event_count' => $digest['event_count'],
            'first_event_id' => $digest['first_event_id'],
            'last_event_id' => $digest['last_event_id'],
            'digest' => $digest['digest'],
            'sealed_at' => now(),
        ]);

        $writer->record([
            'category' => 'security',
            'action' => 'audit.daily-sealed',
            'source' => 'console',
            'metadata' => array_merge(['audit_date' => $date], $digest),
        ], true);

        $this->info("Audit seal created for {$date}: {$digest['event_count']} events.");
        return self::SUCCESS;
    }
}
