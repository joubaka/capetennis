<?php

namespace Tests\Unit;

use App\Support\PhoneContact;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneContactTest extends TestCase
{
    #[DataProvider('phoneNumbers')]
    public function test_whatsapp_links_normalize_contacts_and_reject_invalid_numbers(?string $phone, ?string $expected): void
    {
        $this->assertSame($expected, PhoneContact::whatsAppUrl($phone));
    }

    public static function phoneNumbers(): array
    {
        return [
            'SA local' => ['0645198507', 'https://wa.me/27645198507'],
            'SA formatted' => [' (064) 519-8507 ', 'https://wa.me/27645198507'],
            'SA international' => ['+27 64 519 8507', 'https://wa.me/27645198507'],
            'SA international prefix' => ['0027 64 519 8507', 'https://wa.me/27645198507'],
            'international' => ['+44 7700 900123', 'https://wa.me/447700900123'],
            'already normalized' => ['27645198507', 'https://wa.me/27645198507'],
            'missing' => [null, null],
            'empty' => ['', null],
            'short' => ['12345', null],
            'incomplete SA' => ['064519850', null],
            'extension' => ['0645198507 ext 2', null],
            'multiple numbers' => ['0645198507/0821234567', null],
            'unsafe content' => ['+27645198507?text=hello', null],
            'excess digits' => ['1234567890123456', null],
        ];
    }
}
