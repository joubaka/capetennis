<?php

namespace App\Support;

final class PhoneContact
{
    public static function whatsAppUrl(?string $phone): ?string
    {
        $number = preg_replace('/[\s().-]+/', '', trim((string) $phone));

        if (str_starts_with($number, '+')) {
            $number = substr($number, 1);
        } elseif (str_starts_with($number, '00')) {
            $number = substr($number, 2);
        } elseif (preg_match('/^0[1-9][0-9]{8}$/', $number)) {
            $number = '27'.substr($number, 1);
        }

        return preg_match('/^[1-9][0-9]{7,14}$/', $number)
            ? 'https://wa.me/'.$number
            : null;
    }
}
