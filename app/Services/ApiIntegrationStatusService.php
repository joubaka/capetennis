<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

class ApiIntegrationStatusService
{
    private const MAX_INTEGRATIONS = 250;
    private const MAX_AUDIT_EVENTS = 2000;

    public function all(): Collection
    {
        $ability = (string) config('integrations.jta.ability', 'jta-results:read');

        $tokens = PersonalAccessToken::query()
            ->with('tokenable:id,name,email')
            ->where('tokenable_type', User::class)
            ->where('abilities', 'like', '%"'.addcslashes($ability, '%_\\').'"%')
            ->latest('id')
            ->limit(self::MAX_INTEGRATIONS)
            ->get();

        $auditEvents = $this->auditEventsFor($tokens->pluck('id'));

        return $tokens->map(fn (PersonalAccessToken $token) => $this->summarize(
            $token,
            $auditEvents->get((int) $token->getKey(), collect()),
        ));
    }

    private function auditEventsFor(Collection $tokenIds): Collection
    {
        if ($tokenIds->isEmpty() || ! Schema::hasTable('audit_events')) {
            return collect();
        }

        $tokenIds = $tokenIds->map(fn ($id) => (int) $id)->all();

        return AuditEvent::query()
            ->where('action', 'integration.jta.access')
            ->latest('id')
            ->limit(self::MAX_AUDIT_EVENTS)
            ->get()
            ->filter(fn (AuditEvent $event) => in_array(
                (int) data_get($event->metadata, 'token_id'),
                $tokenIds,
                true,
            ))
            ->groupBy(fn (AuditEvent $event) => (int) data_get($event->metadata, 'token_id'));
    }

    private function summarize(PersonalAccessToken $token, Collection $events): array
    {
        $latestAttempt = $events->sortByDesc('occurred_at')->first();
        $latestSuccess = $events
            ->where('outcome', 'succeeded')
            ->sortByDesc('occurred_at')
            ->first();
        $requestsLast24Hours = $events
            ->filter(fn (AuditEvent $event) => $event->occurred_at?->gte(now()->subDay()))
            ->count();

        [$status, $label, $detail, $colour] = $this->statusFor($token, $latestAttempt, $latestSuccess);

        return [
            'id' => (int) $token->getKey(),
            'name' => $token->name,
            'owner' => $token->tokenable,
            'status' => $status,
            'status_label' => $label,
            'status_detail' => $detail,
            'status_colour' => $colour,
            'last_used_at' => $token->last_used_at,
            'expires_at' => $token->expires_at,
            'latest_attempt_at' => $latestAttempt?->occurred_at,
            'latest_success_at' => $latestSuccess?->occurred_at,
            'latest_status_code' => $latestAttempt?->status_code,
            'latest_endpoint' => data_get($latestAttempt?->metadata, 'endpoint'),
            'requests_last_24_hours' => $requestsLast24Hours,
        ];
    }

    private function statusFor(
        PersonalAccessToken $token,
        ?AuditEvent $latestAttempt,
        ?AuditEvent $latestSuccess,
    ): array {
        if ($token->expires_at?->isPast()) {
            return ['expired', 'Expired', 'The API key has expired and must be rotated.', 'danger'];
        }

        if ($latestAttempt && $latestAttempt->outcome !== 'succeeded'
            && (! $latestSuccess || $latestAttempt->occurred_at->gt($latestSuccess->occurred_at))) {
            if ((int) $latestAttempt->status_code === 429 && $latestSuccess) {
                return ['rate_limited', 'Rate limited', 'Connected, but Cape Tennis recently limited excessive requests.', 'warning'];
            }

            return ['needs_attention', 'Needs attention', 'Cape Tennis received a request, but the latest attempt failed.', 'danger'];
        }

        if ($latestSuccess?->occurred_at?->gte(now()->subDay())) {
            return ['active', 'Active', 'Successfully connected to Cape Tennis in the last 24 hours.', 'success'];
        }

        if ($latestSuccess?->occurred_at?->gte(now()->subDays(7))) {
            return ['connected', 'Connected', 'The API connection succeeded during the last 7 days.', 'success'];
        }

        if ($latestSuccess) {
            return ['inactive', 'No recent activity', 'Previously connected, but no successful request was seen in the last 7 days.', 'secondary'];
        }

        if ($token->last_used_at) {
            return ['connecting', 'Trying to connect', 'Cape Tennis has detected use of the API key, but no successful API request yet.', 'warning'];
        }

        return ['awaiting_connection', 'Awaiting first connection', 'Access is configured, but the academy or website has not contacted Cape Tennis yet.', 'info'];
    }
}
