<?php

namespace App\Support\Audit;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use JsonSerializable;
use Illuminate\Database\Eloquent\Model;

class AuditRedactor
{
    public function redact(mixed $value, int $depth = 0, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitive($key)) {
            return '[REDACTED]';
        }

        if ($depth >= (int) config('audit.max_depth', 6)) {
            return '[MAX_DEPTH]';
        }

        if ($value instanceof UploadedFile) {
            return [
                'file_name' => $value->getClientOriginalName(),
                'mime_type' => $value->getClientMimeType(),
                'size' => $value->getSize(),
            ];
        }

        if ($value instanceof Model) {
            return [
                'type' => $value::class,
                'id' => $value->getKey(),
            ];
        }

        if (is_resource($value)) {
            return '[RESOURCE]';
        }

        if ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        } elseif (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                $value = $value->toArray();
            } else {
                return ['class' => $value::class];
            }
        }

        if (is_array($value)) {
            $sensitiveValue = false;
            foreach (['key', 'name', 'setting'] as $field) {
                if (isset($value[$field]) && is_scalar($value[$field])
                    && $this->isSensitive((string) $value[$field])) {
                    $sensitiveValue = true;
                    break;
                }
            }
            $redacted = [];
            foreach ($value as $childKey => $childValue) {
                $redacted[$childKey] = $sensitiveValue && (string) $childKey === 'value'
                    ? '[REDACTED]'
                    : $this->redact($childValue, $depth + 1, (string) $childKey);
            }
            return $redacted;
        }

        if (is_string($value)) {
            return Str::limit($value, (int) config('audit.max_string_length', 2000), '…');
        }

        return $value;
    }

    private function isSensitive(string $key): bool
    {
        $normalized = Str::lower(str_replace(['-', '.'], '_', $key));

        foreach (config('audit.sensitive_keys', []) as $sensitive) {
            $sensitive = Str::lower($sensitive);
            if ($normalized === $sensitive || str_ends_with($normalized, '_'.$sensitive)) {
                return true;
            }
        }

        return false;
    }
}
