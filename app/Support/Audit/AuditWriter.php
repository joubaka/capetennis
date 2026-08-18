<?php

namespace App\Support\Audit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\CarbonInterface;
use RuntimeException;
use Throwable;

class AuditWriter
{
    private bool $tableConfirmed = false;

    public function __construct(
        private readonly AuditContext $context,
        private readonly AuditRedactor $redactor,
    ) {}

    public function record(array $event, bool $critical = false): ?int
    {
        if (! config('audit.enabled', true)) {
            return null;
        }

        try {
            if (! $this->tableConfirmed && ! Schema::hasTable('audit_events')) {
                if ($critical
                    && config('audit.require_table', true)
                    && ! app()->runningUnitTests()
                    && ! $this->runningMigrationCommand()) {
                    throw new RuntimeException('The required audit_events table is missing.');
                }
                return null;
            }
            $this->tableConfirmed = true;

            $payload = $this->prepare($event);
            return (int) DB::table('audit_events')->insertGetId($payload);
        } catch (Throwable $exception) {
            Log::error('Canonical audit write failed.', [
                'action' => $event['action'] ?? null,
                'critical' => $critical,
                'error' => $exception->getMessage(),
            ]);

            if ($critical && config('audit.fail_closed', true)) {
                throw $exception;
            }

            return null;
        }
    }

    private function prepare(array $event): array
    {
        $request = $this->context->request();
        $actor = $event['actor'] ?? Auth::user();
        $subject = $event['subject'] ?? null;
        $occurredAt = $event['occurred_at'] ?? now();
        $occurredAt = $occurredAt instanceof CarbonInterface
            ? $occurredAt->format('Y-m-d H:i:s.u')
            : (string) $occurredAt;
        $createdAt = now()->format('Y-m-d H:i:s.u');
        $eventUuid = (string) Str::ulid();

        $payload = [
            'event_uuid' => $eventUuid,
            'occurred_at' => $occurredAt,
            'category' => $event['category'] ?? 'business',
            'action' => $event['action'],
            'outcome' => $event['outcome'] ?? 'succeeded',
            'source' => $event['source'] ?? ($request ? 'web' : (app()->runningInConsole() ? 'console' : 'system')),
            'actor_id' => $actor instanceof Authenticatable ? $actor->getAuthIdentifier() : ($event['actor_id'] ?? null),
            'actor_type' => $event['actor_type'] ?? ($actor ? 'user' : 'system'),
            'actor_name' => $actor?->name ?? $actor?->userName ?? ($event['actor_name'] ?? null),
            'actor_email' => $actor?->email ?? ($event['actor_email'] ?? null),
            'actor_roles' => $this->json($this->rolesFor($actor)),
            'subject_type' => $subject instanceof Model ? $subject::class : ($event['subject_type'] ?? null),
            'subject_id' => $subject instanceof Model ? (string) $subject->getKey() : (isset($event['subject_id']) ? (string) $event['subject_id'] : null),
            'subject_label' => $event['subject_label'] ?? $this->labelFor($subject),
            'event_id' => $event['event_id'] ?? $this->eventIdFor($subject),
            'request_id' => $event['request_id'] ?? $this->context->requestId(),
            'journey_id' => $event['journey_id'] ?? $this->context->journeyId(),
            'previous_request_id' => $event['previous_request_id'] ?? $this->context->previousRequestId(),
            'batch_id' => $event['batch_id'] ?? null,
            'route_name' => $event['route_name'] ?? $request?->route()?->getName(),
            'http_method' => $event['http_method'] ?? $request?->method(),
            'path' => $event['path'] ?? $request?->path(),
            'referrer' => $event['referrer'] ?? $request?->headers->get('referer'),
            'status_code' => $event['status_code'] ?? null,
            'ip_address' => $event['ip_address'] ?? $request?->ip(),
            'user_agent' => Str::limit((string) ($event['user_agent'] ?? $request?->userAgent()), 1000, '…') ?: null,
            'before' => $this->json($this->redactor->redact($event['before'] ?? null)),
            'after' => $this->json($this->redactor->redact($event['after'] ?? null)),
            'metadata' => $this->json($this->redactor->redact($event['metadata'] ?? null)),
            'reason' => isset($event['reason']) ? Str::limit((string) $event['reason'], 2000, '…') : null,
            'created_at' => $createdAt,
        ];

        $payload['integrity_hash'] = AuditIntegrity::hash($payload);

        return $payload;
    }

    private function json(mixed $value): ?string
    {
        return $value === null || $value === []
            ? null
            : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function rolesFor(mixed $actor): array
    {
        if (! $actor || ! method_exists($actor, 'getRoleNames')) {
            return [];
        }

        try {
            return $actor->getRoleNames()->values()->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function labelFor(mixed $subject): ?string
    {
        if (! $subject instanceof Model) {
            return null;
        }

        foreach (['name', 'title', 'description', 'email', 'reference'] as $attribute) {
            $value = $subject->getAttribute($attribute);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return Str::limit((string) $value, 255, '…');
            }
        }

        return class_basename($subject).' #'.$subject->getKey();
    }

    private function eventIdFor(mixed $subject): ?int
    {
        if (! $subject instanceof Model) {
            return null;
        }

        foreach (['event_id', 'eventId'] as $attribute) {
            $value = $subject->getAttribute($attribute);
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return $subject instanceof \App\Models\Event ? (int) $subject->getKey() : null;
    }

    private function runningMigrationCommand(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        $arguments = $_SERVER['argv'] ?? [];
        return collect($arguments)->contains(fn ($argument) => is_string($argument)
            && (str_starts_with($argument, 'migrate') || $argument === 'test'));
    }
}
