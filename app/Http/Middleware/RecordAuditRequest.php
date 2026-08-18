<?php

namespace App\Http\Middleware;

use App\Support\Audit\AuditContext;
use App\Support\Audit\AuditWriter;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class RecordAuditRequest
{
    public function __construct(
        private readonly AuditContext $context,
        private readonly AuditWriter $writer,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('audit.enabled', true) || $this->excluded($request)) {
            return $next($request);
        }

        $requestId = $this->requestId($request);
        $journeyId = $this->journeyId($request);
        $previousRequestId = $request->hasSession() ? $request->session()->get('audit_previous_request_id') : null;
        $this->context->set($request, $requestId, $journeyId, $previousRequestId);

        try {
            if ($this->potentialMutation($request)) {
                $routeName = $request->route()?->getName();
                $this->writer->record([
                    'category' => 'request',
                    'action' => $routeName ? 'route.'.$routeName.'.attempted' : 'request.mutation-attempted',
                    'outcome' => 'attempted',
                    'metadata' => [
                        'route_action' => $request->route()?->getActionName(),
                        'route_parameters' => $request->route()?->parameters() ?? [],
                        'input' => $request->except(['_token']),
                    ],
                ], true);
            }

            $response = $next($request);
            $this->record($request, $response->getStatusCode());
            $response->headers->set('X-Request-Id', $requestId);

            if ($request->hasSession()) {
                $request->session()->put('audit_previous_request_id', $requestId);
            }

            return $response;
        } catch (Throwable $exception) {
            $statusCode = match (true) {
                $exception instanceof AuthorizationException => 403,
                $exception instanceof AuthenticationException => 401,
                $exception instanceof ValidationException => $exception->status,
                $exception instanceof TokenMismatchException => 419,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };
            $outcome = in_array($statusCode, [401, 403, 419], true) ? 'denied' : 'failed';
            $this->writer->record([
                'category' => 'request',
                'action' => $outcome === 'denied' ? 'request.denied' : 'request.failed',
                'outcome' => $outcome,
                'status_code' => $statusCode,
                'metadata' => [
                    'route_action' => $request->route()?->getActionName(),
                    'exception' => $exception::class,
                ],
            ]);
            throw $exception;
        } finally {
            $this->context->clear();
        }
    }

    private function record(Request $request, int $statusCode): void
    {
        $method = strtoupper($request->method());
        $isPage = in_array($method, ['GET', 'HEAD'], true);
        if ($isPage && ! auth()->check() && ! config('audit.public_page_views', true)) {
            return;
        }

        $subject = collect($request->route()?->parameters() ?? [])
            ->first(fn ($parameter) => $parameter instanceof Model);
        $outcome = match (true) {
            in_array($statusCode, [401, 403, 419], true) => 'denied',
            $statusCode >= 400 => 'failed',
            default => 'succeeded',
        };

        $routeName = $request->route()?->getName();
        $this->writer->record([
            'category' => $isPage ? 'navigation' : 'request',
            'action' => $isPage ? 'page.viewed' : ($routeName ? 'route.'.$routeName : 'request.mutated'),
            'outcome' => $outcome,
            'status_code' => $statusCode,
            'subject' => $subject,
            'metadata' => [
                'route_action' => $request->route()?->getActionName(),
                'route_parameters' => $request->route()?->parameters() ?? [],
                'input' => $isPage ? null : $request->except(['_token']),
                'ajax' => $request->ajax(),
            ],
        ]);
    }

    private function excluded(Request $request): bool
    {
        $path = ltrim($request->path(), '/');
        foreach (config('audit.excluded_route_prefixes', []) as $prefix) {
            if (Str::startsWith($path, $prefix)) {
                return true;
            }
        }
        return false;
    }

    private function requestId(Request $request): string
    {
        $provided = $request->headers->get('X-Request-Id');
        return is_string($provided) && preg_match('/^[A-Za-z0-9._-]{8,36}$/', $provided)
            ? $provided
            : (string) Str::uuid();
    }

    private function potentialMutation(Request $request): bool
    {
        if (! in_array(strtoupper($request->method()), ['GET', 'HEAD'], true)) {
            return true;
        }

        $route = strtolower((string) ($request->route()?->getName().' '.$request->route()?->getActionMethod()));
        return preg_match('/(^|[. _-])(save|store|update|delete|destroy|remove|clear|reset|cancel|toggle|publish|withdraw|refund|approve|reject|complete|waive)([. _-]|$)/', $route) === 1;
    }

    private function journeyId(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $journeyId = $request->session()->get('audit_journey_id');
        if (! is_string($journeyId) || strlen($journeyId) !== 26) {
            $journeyId = (string) Str::ulid();
            $request->session()->put('audit_journey_id', $journeyId);
        }
        return $journeyId;
    }
}
