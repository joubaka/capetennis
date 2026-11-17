<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** File-only transport observations: identifiers here have NOT been authenticated. */
class PayfastCallbackTelemetry
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! in_array($request->path(), [
            'notify', 'notify_team', 'notifyClothing', 'notify_order', 'notify_hoodie',
        ], true)) {
            return $next($request);
        }

        // PayFast sends form data. Do not lazily decode JSON before ValidatePostSize.
        $form = $request->request->all();
        $context = [
            'correlation_id' => (string) Str::uuid(),
            'endpoint' => $request->path(),
            'unverified_provider_reference' => $this->identifier($form['pf_payment_id'] ?? null),
            'unverified_order_id' => $this->identifier($form['custom_int5'] ?? null),
        ];
        $request->attributes->set('payfast_callback_correlation_id', $context['correlation_id']);
        $this->write('payfast.callback.ingress', $context);
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->write('payfast.callback.outcome', $context + [
                // The outer exception handler has not rendered a wire response yet.
                'inferred_http_status' => $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500,
                'exception_class' => $exception::class,
            ]);
            throw $exception;
        }
        $this->write('payfast.callback.outcome', $context + ['http_status' => $response->getStatusCode()]);

        return $response;
    }

    private function identifier(mixed $value): ?string
    {
        return is_scalar($value) && preg_match('/\A[A-Za-z0-9_-]{1,64}\z/D', (string) $value)
            ? (string) $value : null;
    }

    protected function write(string $message, array $context): void
    {
        try {
            // A dedicated local file avoids database, session, and notification transports.
            Log::build(['driver' => 'daily', 'days' => 14, 'path' => storage_path('logs/payfast-callbacks.log')])
                ->info($message, $context);
        } catch (Throwable) {
            // Observability must never change the provider's financial response.
        }
    }
}
