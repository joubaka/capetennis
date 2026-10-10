<?php

namespace App\Services\Performance;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Deliberately excludes exception messages, traces, actors and source records. */
class PlayerRatingRefreshDiagnostics
{
    public static function reference(?string $value = null): string
    {
        return $value && Str::isUuid($value) ? $value : (string) Str::uuid();
    }

    public static function write(string $reference, string $phase, string $reason, ?\Throwable $error = null, ?int $exitCode = null): void
    {
        $context = ['reference_id' => self::reference($reference), 'phase' => $phase, 'reason' => $reason];
        if ($error) {
            $context['exception_category'] = $error instanceof \Illuminate\Database\QueryException || $error instanceof \PDOException
                ? 'database' : ($error instanceof \Illuminate\Process\Exceptions\ProcessTimedOutException ? 'process_timeout' : 'unexpected');
        }
        if ($exitCode !== null) $context['exit_code'] = $exitCode;
        try { Log::log($error || $exitCode ? 'warning' : 'info', 'Player ratings refresh diagnostic.', $context); }
        catch (\Throwable) { /* Diagnostics must never prevent owner-scoped cleanup. */ }
    }
}
