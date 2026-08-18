<?php

namespace Tests\Unit;

use App\Support\Audit\AuditRedactor;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditRedactorTest extends TestCase
{
    #[DataProvider('sensitiveKeys')]
    public function test_sensitive_values_are_redacted(string $key): void
    {
        $result = app(AuditRedactor::class)->redact([$key => 'must-not-leak']);

        $this->assertSame('[REDACTED]', $result[$key]);
    }

    public static function sensitiveKeys(): array
    {
        return [
            ['password'],
            ['password_confirmation'],
            ['two_factor_secret'],
            ['refund_account_number'],
            ['payfast_passphrase'],
            ['api_access_token'],
            ['payfast_raw_data'],
        ];
    }

    public function test_nested_values_are_redacted_without_losing_safe_context(): void
    {
        $result = app(AuditRedactor::class)->redact([
            'player' => ['name' => 'Ava', 'bank_account_number' => '123456'],
        ]);

        $this->assertSame('Ava', $result['player']['name']);
        $this->assertSame('[REDACTED]', $result['player']['bank_account_number']);
    }

    public function test_generic_setting_value_is_redacted_when_its_key_names_a_secret(): void
    {
        $result = app(AuditRedactor::class)->redact([
            'key' => 'payfast_passphrase',
            'value' => 'must-not-leak',
        ]);

        $this->assertSame('payfast_passphrase', $result['key']);
        $this->assertSame('[REDACTED]', $result['value']);
    }
}
