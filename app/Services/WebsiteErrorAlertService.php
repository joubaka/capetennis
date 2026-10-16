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
                    'trace' => array_map(fn (array $frame): array => [
                        'file' => $this->relativeFile($frame['file'] ?? ''),
                        'line' => $frame['line'] ?? null,
                    ], array_slice($exception->getTrace(), 0, 25)),
                ];

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
}
