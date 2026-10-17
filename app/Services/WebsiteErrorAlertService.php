<?php

namespace App\Services;

use App\Jobs\SendWebsiteErrorAlert;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class WebsiteErrorAlertService
{
    private static bool $reporting = false;

    public function report(Throwable $exception): void
    {
        if (self::$reporting || app()->runningInConsole() || ! app()->environment(['production', 'staging'])) {
            return;
        }

        if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500) {
            return;
        }

        self::$reporting = true;

        try {
            // Do not fingerprint messages: they can contain SQL bindings or secrets.
            $fingerprint = hash('sha256', $exception::class.'|'.$exception->getFile().'|'.$exception->getLine());
            if (! Cache::add('website-error-alert:'.$fingerprint, true, now()->addMinutes(5))) {
                return;
            }

            // Bound distinct failures too; ordinary Laravel logging remains unchanged.
            RateLimiter::attempt('website-error-alert:global', 10, function () use ($exception, $fingerprint): void {
                $request = request();
                $diagnostic = [
                    'reference' => (string) Str::uuid(),
                    'fingerprint' => $fingerprint,
                    'occurred_at' => now()->toIso8601String(),
                    'environment' => app()->environment(),
                    'exception' => $exception::class,
                    'file' => $this->relativeFile($exception->getFile()),
                    'line' => $exception->getLine(),
                    'method' => $request->method(),
                    // The route pattern contains placeholders, never signed URLs or path tokens.
                    'route' => $request->route()?->uri() ?? '(unmatched route)',
                    'user_id' => $request->user()?->getAuthIdentifier(),
                    'exception_log' => $this->exceptionLog($exception),
                    'trace' => array_map(fn (array $frame): array => [
                        'file' => $this->relativeFile($frame['file'] ?? ''),
                        'line' => $frame['line'] ?? null,
                    ], array_slice($exception->getTrace(), 0, 25)),
                ];

                Log::notice('Website error alert reference.', array_intersect_key($diagnostic, array_flip([
                    'reference', 'fingerprint', 'occurred_at', 'exception', 'file', 'line',
                ])));

                User::role('super-user', 'web')->select(['users.id'])->chunkById(100, function ($admins) use ($diagnostic): void {
                    foreach ($admins as $admin) {
                        SendWebsiteErrorAlert::dispatch($admin->id, $diagnostic);
                    }
                });
            }, 60);
        } catch (Throwable $alertFailure) {
            // Never report() a delivery failure or leak its potentially sensitive message.
            try {
                Log::warning('Website error alert could not be queued.', ['exception' => $alertFailure::class]);
            } catch (Throwable) {
                // A failing logger must not replace the original website error either.
            }
        } finally {
            self::$reporting = false;
        }
    }

    private function relativeFile(string $file): string
    {
        $base = str_replace('\\', '/', base_path()).'/';
        $file = str_replace('\\', '/', $file);

        return str_starts_with($file, $base) ? substr($file, strlen($base)) : basename($file);
    }

    private function exceptionLog(Throwable $exception): string
    {
        $entries = [];
        do {
            // Query messages contain SQL and bindings, even when trace arguments are disabled.
            $message = $exception instanceof \Illuminate\Database\QueryException || preg_match('/\b(SQL|SQLSTATE)\b/i', $exception->getMessage())
                ? '[Database error details redacted]'
                : $this->redactMessage($exception->getMessage());
            $lines = [$exception::class.': '.$message, 'at '.$this->relativeFile($exception->getFile()).':'.$exception->getLine()];
            foreach ($exception->getTrace() as $index => $frame) {
                $lines[] = '#'.$index.' '.$this->relativeFile($frame['file'] ?? '').':'.($frame['line'] ?? '?').' '.($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '').'()';
            }
            $entries[] = implode("\n", $lines);
        } while ($exception = $exception->getPrevious());

        return implode("\n\nCaused by:\n", $entries);
    }

    private function redactMessage(string $message): string
    {
        // Only retain known engine diagnostics with no dynamic values. Arbitrary messages
        // can contain credentials or personal data even when request inputs are removed.
        if (preg_match('/^Call to a member function ([A-Za-z_][A-Za-z0-9_]*)\(\) on (null|bool|int|float|string)\b/', $message, $matches)) {
            return $matches[0];
        }
        if (in_array($message, ['Division by zero', 'Array to string conversion', 'Attempt to read property on null'], true)) {
            return $message;
        }

        return '[Message redacted; full message is available in restricted application logs]';
    }
}
