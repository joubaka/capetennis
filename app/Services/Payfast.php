<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationOrder;
use App\Models\User;
use App\Models\Player;
use Illuminate\Support\Facades\Http;

class Payfast
{
  /* =====================================================
   * CORE PAYMENT DATA
   * ===================================================== */
  public $amount = null;
  public $item_name = null;

  /* =====================================================
   * PAYFAST CUSTOM FIELDS
   * ===================================================== */
  public $custom_int1 = null;
  public $custom_int2 = null;
  public $custom_int3 = null; // event_id
  public $custom_int4 = null; // registration_id
  public $custom_int5 = null; // order_id

  public $custom_str1 = null;
  public $custom_str2 = null;
  public $custom_str3 = null;
  public $custom_str4 = null; // payer name
  public $custom_str5 = null; // used for TeamOrder flag

  /* =====================================================
   * PAYFAST CONFIG
   * ===================================================== */
  public $payfast_url;
  public $sandbox_url;

  public $api_url = 'https://api.payfast.co.za';
  public $api_sandbox_url = 'https://api.payfast.co.za';  // same host, testing=true param used

  public $payfast_id;
  public $sandbox_id;

  public $payfast_key;
  public $sandbox_key;

  public $notify_url;
  public $notify_url_team;
  public $cancel_url;
  public $return_url;

  public $mode = 'live';

  public $url;
  public $id;
  public $key;

  /* =====================================================
   * CONSTRUCTOR
   * ===================================================== */
  public function __construct()
  {
    $this->payfast_url = 'https://www.payfast.co.za/eng/process';
    $this->sandbox_url = 'https://sandbox.payfast.co.za/eng/process';

    $this->payfast_id = '11307280';
    $this->sandbox_id = '10008657';

    $this->payfast_key = 'cnewg4817uvaq';
    $this->sandbox_key = 'elbe10m0u0daf';

    $this->notify_url = 'https://www.capetennis.co.za/notify';
    $this->notify_url_team = 'https://www.capetennis.co.za/notify_team';
    $this->cancel_url = 'https://www.capetennis.co.za/cancel';
    $this->return_url = 'https://www.capetennis.co.za';

    // default = live
    $this->url = $this->payfast_url;
    $this->id = $this->payfast_id;
    $this->key = $this->payfast_key;
  }

  /* =====================================================
   * MODE
   * ===================================================== */
  public function setMode(int $type): void
  {
    if ($type === 1) {
      $this->url = $this->payfast_url;
      $this->id = $this->payfast_id;
      $this->key = $this->payfast_key;
      $this->mode = 'live';

    } elseif ($type === 0) {
      $this->url = $this->sandbox_url;
      $this->id = $this->sandbox_id;
      $this->key = $this->sandbox_key;
      $this->mode = 'sandbox';

    } elseif ($type === 2) {
      $this->mode = 'test';

    } else {
      throw new \InvalidArgumentException('Invalid PayFast mode');
    }
  }

  /* =====================================================
   * SAFE URL NORMALIZER
   * ===================================================== */
  private function normalizeUrl(string $url): string
  {
    if (filter_var($url, FILTER_VALIDATE_URL)) {
      return $url;
    }

    return rtrim(url('/'), '/') . '/' . ltrim($url, '/');
  }

  /* =====================================================
   * URL SETTERS (SAFE)
   * ===================================================== */
  public function setNotifyUrl(string $url): void
  {
    $this->notify_url = $this->normalizeUrl($url);
  }

  public function setTeamNotifyUrl(string $url): void
  {
    $this->notify_url_team = $this->normalizeUrl($url);
  }

  public function setReturnUrl(string $url): void
  {
    $this->return_url = $this->normalizeUrl($url);
  }

  public function setCancelUrl(string $url): void
  {
    $this->cancel_url = $this->normalizeUrl($url);
  }

  /* =====================================================
   * DOMAIN OBJECT SETTERS
   * ===================================================== */
  public function setEvent(Event $event): void
  {
    $this->custom_int3 = $event->id;
    $this->custom_str3 = $event->name;
    $this->item_name = $event->name;
  }

  public function setRegistration(Registration $registration): void
  {
    $this->custom_int4 = $registration->id;
  }

  public function setOrder(RegistrationOrder $order): void
  {
    $this->custom_int5 = $order->id;
  }

  public function setPayer(User $user): void
  {
    $this->custom_str4 = $user->name;
  }

  public function setPlayerInfo(?Player $player): void
  {
    if ($player) {
      $this->custom_int2 = $player->id;
      $this->custom_str2 = $player->name . ' ' . $player->surname;
    }
  }

  public function setCategoryEventId(?int $categoryEventId): void
  {
    $this->custom_int1 = $categoryEventId;
  }

  public function setCategoryEvent(CategoryEvent $categoryEvent): void
  {
    $this->custom_int1 = $categoryEvent->id;
    $this->custom_str1 = optional($categoryEvent->category)->name;
  }

  /* =====================================================
   * AMOUNT
   * ===================================================== */
  public function setAmount(float $amount): void
  {
    $this->amount = number_format($amount, 2, '.', '');
  }

  public function setItem(string $item): void
  {
    $this->item_name = $item;
  }

  /* =====================================================
   * FORM SIGNATURE GENERATOR
   * PayFast validates the checkout form signature using the fields
   * in the EXACT ORDER they are posted — NOT alphabetically sorted.
   * Passphrase is appended last after all real fields.
   * ===================================================== */
  public function generateFormSignature(array $fields): string
  {
    $passphrase = $this->mode === 'sandbox'
      ? (config('services.payfast.passphrase_sandbox') ?: config('services.payfast.passphrase'))
      : (config('services.payfast.passphrase_live') ?: config('services.payfast.passphrase'));

    // Remove empty values and signature/passphrase
    $data = array_filter($fields, fn($v) => $v !== null && $v !== '');
    unset($data['signature'], $data['passphrase']);

    // Add passphrase into the array, sort alphabetically, then http_build_query
    // per PayFast docs: sort all variables alphabetically before hashing
    if (!empty($passphrase)) {
      $data['passphrase'] = trim($passphrase);
    }
    ksort($data);

    return md5(http_build_query($data));
  }


  /* =====================================================
   * BUILD FORM
   * ===================================================== */
  public function getForm(): string
  {
    // Decide notify endpoint based on order type
    $notifyUrl = $this->custom_str5 === 'TeamOrder'
      ? $this->notify_url_team
      : $this->notify_url;

    $fields = [
      'merchant_id' => $this->id,
      'merchant_key' => $this->key,
      'return_url' => $this->return_url,
      'cancel_url' => $this->cancel_url,
      'notify_url' => $notifyUrl,
      'amount' => $this->amount,
      'item_name' => $this->item_name,

      'custom_int1' => $this->custom_int1,
      'custom_int2' => $this->custom_int2,
      'custom_int3' => $this->custom_int3,
      'custom_int4' => $this->custom_int4,
      'custom_int5' => $this->custom_int5,

      'custom_str1' => $this->custom_str1,
      'custom_str2' => $this->custom_str2,
      'custom_str3' => $this->custom_str3,
      'custom_str4' => $this->custom_str4,
      'custom_str5' => $this->custom_str5,
    ];

    $signature = $this->generateFormSignature($fields);

    $html = '<form id="payfastForm" action="' . $this->url . '" method="post">';

    foreach ($fields as $name => $value) {
      if ($value !== null && $value !== '') {
        $html .= '<input type="hidden" name="' . $name . '" value="' . e($value) . '">';
      }
    }

    $html .= '<input type="hidden" name="signature" value="' . $signature . '">';
    $html .= '</form>';

    return $html;
  }

  /* =====================================================
   * SIGNED HEADER BUILDER (shared by refund + refundQuery)
   * ===================================================== */
  private function buildApiHeaders(array $bodyParams = []): array
  {
    $passphrase = $this->mode === 'sandbox'
      ? (config('services.payfast.passphrase_sandbox') ?: config('services.payfast.passphrase'))
      : (config('services.payfast.passphrase_live') ?: config('services.payfast.passphrase'));

    $timestamp = now()->toIso8601String();

    // Combine header params + body params for signing (PayFast requires both)
    $allParams = array_merge([
      'merchant-id' => $this->id,
      'timestamp'   => $timestamp,
      'version'     => 'v1',
    ], $bodyParams);

    if (!empty($passphrase)) {
      $allParams['passphrase'] = $passphrase;
    }

    ksort($allParams);
    $signatureString = http_build_query($allParams);
    $signature = md5($signatureString);

    \Log::debug('PayFast buildApiHeaders', [
      'mode'             => $this->mode,
      'merchant_id'      => $this->id,
      'passphrase_set'   => !empty($passphrase),
      'timestamp'        => $timestamp,
      'signature_string' => $signatureString,
      'signature'        => $signature,
    ]);

    return [
      'merchant-id' => $this->id,
      'version'     => 'v1',
      'timestamp'   => $timestamp,
      'signature'   => $signature,
    ];
  }

  /**
   * Query the status of a previously issued refund via PayFast API.
   *
   * @param string $pf_payment_id
   * @return array{success: bool, data: array|null, error: string|null}
   */
  public function refundQuery(string $pf_payment_id): array
  {
    $testing = $this->mode === 'sandbox';
    $apiUrl = 'https://api.payfast.co.za/refunds/query/' . urlencode($pf_payment_id)
      . ($testing ? '?testing=true' : '');

    $headers = $this->buildApiHeaders();

    \Log::info('PayFast refund query request', [
      'pf_payment_id' => $pf_payment_id,
      'api_url'       => $apiUrl,
      'headers_sent'  => [
        'merchant-id' => $headers['merchant-id'],
        'version'     => $headers['version'],
        'timestamp'   => $headers['timestamp'],
        'signature'   => $headers['signature'],
      ],
    ]);

    try {
      $response = Http::timeout(15)
        ->withHeaders($headers)
        ->get($apiUrl);

      \Log::info('PayFast refund query response', [
        'pf_payment_id'  => $pf_payment_id,
        'http_status'    => $response->status(),
        'response_body'  => $response->body(),
        'response_json'  => $response->json(),
      ]);

      if ($response->successful()) {
        return [
          'success' => true,
          'data'    => $response->json(),
          'error'   => null,
        ];
      }

      return [
        'success' => false,
        'data'    => $response->json(),
        'error'   => 'HTTP ' . $response->status() . ': ' . $response->body(),
      ];

    } catch (\Throwable $e) {
      \Log::error('PayFast refund query exception', [
        'pf_payment_id' => $pf_payment_id,
        'exception'     => get_class($e),
        'message'       => $e->getMessage(),
        'file'          => $e->getFile(),
        'line'          => $e->getLine(),
      ]);

      return [
        'success' => false,
        'data'    => null,
        'error'   => $e->getMessage(),
      ];
    }
  }

  /**
   * Issue a refund via PayFast Refunds API.
   *
   * @param string $pf_payment_id
   * @param float|string $amount
   * @param string $reason
   * @return array{success: bool, data: array|null, error: string|null}
   */
  public function refund(string $pf_payment_id, $amount, string $reason = 'Event withdrawal refund', array $extraBody = []): array
  {
    // PayFast requires amount in CENTS (integer), not rands
    $amountCents = (int) round((float) $amount * 100);

    $testing = $this->mode === 'sandbox';
    // pf_payment_id goes in the URL path: POST /refunds/{id}
    $apiUrl = 'https://api.payfast.co.za/refunds/' . urlencode($pf_payment_id)
      . ($testing ? '?testing=true' : '');

    // Body must NOT include merchant_id/merchant_key (those go in headers only)
    $body = array_merge([
      'amount'           => $amountCents,
      'reason'           => $reason,
      'notify_buyer'     => 1,
      'notify_merchant'  => 0,
    ], $extraBody);

    // Signature must cover both headers AND body params (alphabetically sorted)
    $headers = $this->buildApiHeaders($body);

    try {
      $response = Http::timeout(15)
        ->withHeaders($headers)
        ->post($apiUrl, $body);

      \Log::info('PayFast refund response', [
        'pf_payment_id' => $pf_payment_id,
        'amount_cents'  => $amountCents,
        'status'        => $response->status(),
        'body'          => $response->body(),
      ]);

      if ($response->successful()) {
        return [
          'success' => true,
          'data'    => $response->json(),
          'error'   => null,
        ];
      }

      return [
        'success' => false,
        'data'    => $response->json(),
        'error'   => 'HTTP ' . $response->status() . ': ' . $response->body(),
      ];

    } catch (\Throwable $e) {
      \Log::error('PayFast refund exception', [
        'pf_payment_id' => $pf_payment_id,
        'amount_cents'  => $amountCents,
        'error'         => $e->getMessage(),
      ]);

      return [
        'success' => false,
        'data'    => null,
        'error'   => $e->getMessage(),
      ];
    }
  }
}
