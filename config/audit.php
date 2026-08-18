<?php

return [
    'enabled' => env('AUDIT_ENABLED', true),
    'require_table' => env('AUDIT_REQUIRE_TABLE', true),
    'fail_closed' => env('AUDIT_FAIL_CLOSED', true),
    'public_page_views' => env('AUDIT_PUBLIC_PAGE_VIEWS', true),
    'max_string_length' => (int) env('AUDIT_MAX_STRING_LENGTH', 2000),
    'max_depth' => (int) env('AUDIT_MAX_DEPTH', 6),
    'retention' => [
        'journey_days' => (int) env('AUDIT_JOURNEY_RETENTION_DAYS', 180),
        'security_days' => (int) env('AUDIT_SECURITY_RETENTION_DAYS', 730),
        'business_days' => (int) env('AUDIT_BUSINESS_RETENTION_DAYS', 2555),
    ],
    'sensitive_keys' => [
        'password', 'password_confirmation', 'current_password', 'new_password',
        'token', 'access_token', 'refresh_token', 'remember_token', '_token',
        'authorization', 'cookie', 'signature', 'passphrase', 'merchant_key',
        'two_factor_secret', 'two_factor_recovery_codes', 'recovery_code',
        'refund_account_number', 'bank_account_number', 'account_number',
        'card_number', 'cvv', 'cvc', 'secret', 'private_key', 'smtp_password',
        'payfast_raw_data',
    ],
    'excluded_models' => [
        App\Models\AuditEvent::class,
        App\Models\PlatformAuditLog::class,
        App\Models\DrawAuditLog::class,
        Spatie\Activitylog\Models\Activity::class,
    ],
    'excluded_route_prefixes' => [
        '_debugbar', '_ignition', 'livewire/preview-file', 'sanctum/csrf-cookie',
    ],
];
