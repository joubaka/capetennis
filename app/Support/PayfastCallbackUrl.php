<?php

namespace App\Support;

use InvalidArgumentException;

/** Callback origins come from deployment configuration, never the browser request. */
final class PayfastCallbackUrl
{
    public static function for(string $endpoint): string
    {
        if (! in_array($endpoint, ['notify', 'notify_team', 'notifyClothing', 'notify_order', 'notify_hoodie'], true)) {
            throw new InvalidArgumentException('Unknown PayFast callback endpoint.');
        }

        $base = rtrim((string) config('app.url'), '/');
        self::validate($base);
        $configured = match ($endpoint) {
            'notify' => config('services.payfast.notify_url'),
            'notify_team' => config('services.payfast.notify_team_url'),
            default => null,
        };
        $url = $base.'/'.$endpoint;
        if (is_string($configured) && $configured !== '') {
            $url = str_starts_with($configured, '/')
                ? $base.$configured
                : $configured;
            self::validate($url);
            if (parse_url($url, PHP_URL_PATH) !== parse_url($base.'/'.$endpoint, PHP_URL_PATH)) {
                throw new InvalidArgumentException('PayFast callback override has the wrong endpoint.');
            }
        }

        return $url;
    }

    private static function validate(string $url): void
    {
        $parts = parse_url($url);
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! is_array($parts)
            || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || (app()->environment('production') && ($parts['scheme'] ?? '') !== 'https')) {
            throw new InvalidArgumentException('PayFast callbacks require a safe canonical URL (HTTPS in production).');
        }
    }
}
