<?php

namespace App\Services\Performance;

use Illuminate\Support\Facades\Http;

/** Server-only PayFast subscription API. Tokens and signed headers are never logged. */
class PlayerPlusSubscriptionGateway
{
    public function cancel(string $token): bool
    {
        $sandbox = (bool) config('services.payfast.sandbox');
        $merchant = config('services.payfast.'.($sandbox ? 'sandbox_id' : 'merchant_id'));
        $passphrase = config('services.payfast.'.($sandbox ? 'passphrase_sandbox' : 'passphrase_live')) ?: config('services.payfast.passphrase');
        if (! filled($merchant) || ! filled($passphrase) || ! preg_match('/^[A-Za-z0-9_-]{1,120}$/D', $token)) return false;
        $headers = ['merchant-id' => (string) $merchant, 'version' => 'v1', 'timestamp' => now()->toIso8601String()];
        $signed = $headers + ['passphrase' => $passphrase];
        ksort($signed);
        $headers['signature'] = md5(http_build_query($signed));
        try {
            $response = Http::timeout(15)->connectTimeout(5)->withHeaders($headers)->put(
                'https://api.payfast.co.za/subscriptions/'.rawurlencode($token).'/cancel'.($sandbox ? '?testing=true' : '')
            );
            return $response->successful() && (int) $response->json('code') === 200
                && $response->json('status') === 'success' && $response->json('data.response') === true;
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return false;
        }
    }
}
