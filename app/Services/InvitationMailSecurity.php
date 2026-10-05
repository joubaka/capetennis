<?php

namespace App\Services;

use App\Models\BulkEmailLog;
use Illuminate\Validation\ValidationException;

class InvitationMailSecurity
{
    public function allowedFromAddresses(): array
    {
        return collect([config('mail.from.address')])
            ->merge(config('mail.allowed_from_addresses', []))
            ->filter()->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->unique()->values()->all();
    }

    public function assertAllowedFrom(string $address): void
    {
        if (! in_array(mb_strtolower(trim($address)), $this->allowedFromAddresses(), true)) {
            throw ValidationException::withMessages(['from_address' => 'Select a configured and verified From address.']);
        }
    }

    public function reviewProof(int $actorId, string $scope, string $requestToken, string $recipientHash, string $compositionHash, int $expiresAt): string
    {
        return hash_hmac('sha256', implode('|', [$actorId, $scope, $requestToken, $recipientHash, $compositionHash, $expiresAt]), (string) config('app.key'));
    }

    public function assertReviewProof(int $actorId, string $scope, array $request): void
    {
        if ((int) $request['review_expires_at'] < now()->getTimestamp()) {
            throw ValidationException::withMessages(['review' => 'This email review expired. Review the recipients and message again.']);
        }
        $expected = $this->reviewProof($actorId, $scope, $request['request_token'], $request['recipient_hash'], $request['composition_hash'], (int) $request['review_expires_at']);
        if (! hash_equals($expected, (string) $request['review_proof'])) {
            throw ValidationException::withMessages(['review' => 'This email review does not belong to this user or invitation batch. Review it again.']);
        }
    }

    public function payloadIntegrity(array $payload): string
    {
        unset($payload['payload_integrity']);
        return $this->signPayload($this->canonicalPayload($payload));
    }

    private function canonicalPayload(array $payload): array
    {
        if (! array_is_list($payload)) ksort($payload);
        foreach ($payload as $key => $value) {
            if (is_array($value)) $payload[$key] = $this->canonicalPayload($value);
        }

        return $payload;
    }

    private function signPayload(array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    private function hasValidIntegrity(array $payload): bool
    {
        if (hash_equals((string) $payload['payload_integrity'], $this->payloadIntegrity($payload))) return true;
        // Retain already-valid historical signatures; never reconstruct or waive a failed proof.
        $signature = (string) $payload['payload_integrity'];
        unset($payload['payload_integrity']);
        ksort($payload);

        return hash_equals($signature, $this->signPayload($payload));
    }

    public function logMatchesSignedSnapshot(BulkEmailLog $log, ?int $eventId = null): bool
    {
        $payload = $log->payload ?? [];
        return isset($payload['payload_integrity'], $payload['recipient_email'], $payload['related_type'], $payload['related_id'])
            && array_key_exists('recipient_name', $payload)
            && $this->hasValidIntegrity($payload)
            && hash_equals((string) $payload['recipient_email'], (string) $log->recipient_email)
            && hash_equals((string) $payload['recipient_name'], (string) $log->recipient_name)
            && hash_equals((string) $payload['related_type'], (string) $log->related_type)
            && (int) $payload['related_id'] === (int) $log->related_id
            && ($eventId === null || (int) ($payload['event_id'] ?? 0) === $eventId);
    }
}
