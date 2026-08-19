<?php

namespace App\Http\Requests\Api\V1;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class JtaPlayerResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'updated_since' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value)
                    || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
                    $fail('The updated_since field must be an ISO-8601 datetime with a timezone.');
                    return;
                }

                try {
                    CarbonImmutable::parse($value);
                } catch (\Throwable) {
                    $fail('The updated_since field must be a valid ISO-8601 datetime.');
                }
            }],
            'page' => ['nullable', 'integer', 'min:1'],
            'cursor' => ['nullable', 'string', 'max:2048'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'full_snapshot' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('full_snapshot')) {
            $boolean = filter_var(
                $this->input('full_snapshot'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if ($boolean !== null) {
                $this->merge(['full_snapshot' => $boolean]);
            }
        }
    }

    public function updatedSince(): ?CarbonImmutable
    {
        if ($this->boolean('full_snapshot') || ! $this->filled('updated_since')) {
            return null;
        }

        return CarbonImmutable::parse((string) $this->input('updated_since'));
    }
}
