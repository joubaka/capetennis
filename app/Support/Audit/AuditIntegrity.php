<?php

namespace App\Support\Audit;

class AuditIntegrity
{
    private const JSON_FIELDS = ['actor_roles', 'before', 'after', 'metadata'];
    private const FIELDS = [
        'event_uuid', 'occurred_at', 'category', 'action', 'outcome', 'source',
        'actor_id', 'actor_type', 'actor_name', 'actor_email', 'actor_roles',
        'subject_type', 'subject_id', 'subject_label', 'event_id', 'request_id',
        'journey_id', 'previous_request_id', 'batch_id', 'route_name', 'http_method',
        'path', 'referrer', 'status_code', 'ip_address', 'user_agent', 'before',
        'after', 'metadata', 'reason', 'created_at',
    ];

    public static function hash(array $payload): string
    {
        $material = [];
        foreach (self::FIELDS as $field) {
            $value = $payload[$field] ?? null;
            if (in_array($field, self::JSON_FIELDS, true) && is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                }
            }
            $material[$field] = self::normalize($value);
        }

        return hash_hmac(
            'sha256',
            json_encode($material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            (string) config('app.key', 'audit-integrity-key')
        );
    }

    public static function verify(array $payload): bool
    {
        $stored = (string) ($payload['integrity_hash'] ?? '');
        return $stored !== '' && hash_equals($stored, self::hash($payload));
    }

    private static function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::normalize($item);
        }

        return $value;
    }
}
