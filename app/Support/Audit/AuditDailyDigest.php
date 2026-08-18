<?php

namespace App\Support\Audit;

use Illuminate\Support\Facades\DB;

class AuditDailyDigest
{
    public function calculate(string $date): array
    {
        $context = hash_init('sha256', HASH_HMAC, (string) config('app.key', 'audit-integrity-key'));
        $count = 0;
        $firstId = null;
        $lastId = null;
        $integrityFailures = 0;

        DB::table('audit_events')
            ->whereDate('occurred_at', $date)
            ->orderBy('id')
            ->chunkById(1000, function ($events) use ($context, &$count, &$firstId, &$lastId, &$integrityFailures): void {
                foreach ($events as $event) {
                    $payload = (array) $event;
                    $calculatedHash = AuditIntegrity::hash($payload);
                    if (! hash_equals((string) $event->integrity_hash, $calculatedHash)) {
                        $integrityFailures++;
                    }
                    $firstId ??= (int) $event->id;
                    $lastId = (int) $event->id;
                    $count++;
                    hash_update($context, $event->id.'|'.$event->event_uuid.'|'.$calculatedHash."\n");
                }
            });

        return [
            'event_count' => $count,
            'first_event_id' => $firstId,
            'last_event_id' => $lastId,
            'digest' => hash_final($context),
            'integrity_failures' => $integrityFailures,
        ];
    }
}
