<?php

namespace App\Http\Middleware;

use App\Models\Player;
use App\Support\Audit\AuditWriter;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditJtaIntegrationAccess
{
    public function __construct(private readonly AuditWriter $writer) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/v1/integrations/jta', 'api/v1/integrations/jta/*')) {
            return $next($request);
        }

        try {
            $response = $next($request);
            $this->record($request, $response->getStatusCode());

            return $response;
        } catch (Throwable $exception) {
            $status = match (true) {
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof ValidationException => $exception->status,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };

            $this->record($request, $status);
            throw $exception;
        }
    }

    private function record(Request $request, int $status): void
    {
        $token = $request->user()?->currentAccessToken();
        $routePlayer = $request->route('player');
        $playerId = $request->attributes->get('jta_linked_player_id');

        if (! $playerId) {
            $playerId = $routePlayer instanceof Player ? $routePlayer->getKey() : $routePlayer;
        }

        $this->writer->record([
            'category' => 'security',
            'action' => 'integration.jta.access',
            'outcome' => match (true) {
                in_array($status, [401, 403], true) => 'denied',
                $status >= 400 => 'failed',
                default => 'succeeded',
            },
            'source' => 'api',
            'status_code' => $status,
            'subject_type' => $playerId ? Player::class : null,
            'subject_id' => $playerId ? (string) $playerId : null,
            'metadata' => [
                'token_id' => $token?->getKey(),
                'token_name' => $token?->name,
                'endpoint' => $request->route()?->getName(),
                'cape_tennis_player_id' => $playerId ? (int) $playerId : null,
            ],
        ]);
    }
}
