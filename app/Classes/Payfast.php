<?php

namespace App\Classes;

use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationOrder;
use App\Models\User;
use App\Models\Player;

class Payfast
{
  /* =====================================================
   * CORE PAYMENT DATA
   * ===================================================== */
  public $amount = null;
  public $item_name = null;

  /* =====================================================
   * PAYFAST CUSTOM FIELDS (LOCKED MEANING)
   * ===================================================== */
  public $custom_int1 = null; // optional legacy
  public $custom_int2 = null; // optional legacy
  public $custom_int3 = null; // event_id
  public $custom_int4 = null; // registration_id
  public $custom_int5 = null; // registration_order_id

  public $custom_str1 = null;
  public $custom_str2 = null;
  public $custom_str3 = null;
  public $custom_str4 = null; // payer name
  public $custom_str5 = null;

  /* =====================================================
   * PAYFAST CONFIG
   * ===================================================== */
  public $payfast_url;
  public $sandbox_url;
  public $payfast_id;
  public $sandbox_id;
  public $payfast_key;
  public $sandbox_key;

  public $notify_url;
  public $notify_url_team;
  public $cancel_url;
  public $return_url;

  public $mode = 'live';
  public $merchant_id;
  public $merchant_key;

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
   * URLS
   * ===================================================== */
  public function setNotifyUrl(string $url): void
  {
    $this->notify_url = url('/') . '/' . ltrim($url, '/');
  }

  public function setReturnUrl(string $url): void
  {
    $this->return_url = url('/') . '/' . ltrim($url, '/');
  }

  public function setCancelUrl(string $url): void
  {
    $this->cancel_url = url('/') . '/' . ltrim($url, '/');
  }

  public function setTeamNotifyUrl(string $url): void
  {
    $this->notify_url_team = url('/') . '/' . ltrim($url, '/');
  }

  /* =====================================================
   * EXPLICIT DOMAIN SETTERS (SAFE)
   * ===================================================== */

  /** Event (REQUIRED) */
  public function setEvent(Event $event): void
  {
    $this->custom_int3 = $event->id;
    $this->custom_str3 = $event->name;
    $this->item_name = $event->name;
  }

  /** Registration (REQUIRED) */
  public function setRegistration(Registration $registration): void
  {
    $this->custom_int4 = $registration->id;
  }

  /** Registration Order (REQUIRED) */
  public function setOrder(RegistrationOrder $order): void
  {
    $this->custom_int5 = $order->id;
  }

  /** Payer / Client (STRING ONLY) */
  public function setPayer(User $user): void
  {
    $this->custom_str4 = $user->name;
  }

  /* =====================================================
   * OPTIONAL / LEGACY HELPERS (DO NOT USE FOR FINANCE)
   * ===================================================== */

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

  /* =====================================================
   * AMOUNT / ITEM
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
   * BUILD PAYFAST FORM (AUTO SUBMIT)
   * ===================================================== */
  public function getForm(): string
  {
    $fields = [
      'merchant_id' => $this->id,
      'merchant_key' => $this->key,
      'return_url' => $this->return_url,
      'cancel_url' => $this->cancel_url,
      'notify_url' => $this->notify_url,
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

    $html = '<form id="payfastForm" action="' . $this->url . '" method="post">';

    foreach ($fields as $name => $value) {
      if ($value !== null && $value !== '') {
        $html .= '<input type="hidden" name="' . $name . '" value="' . e($value) . '">';
      }
    }

    $html .= '</form>';

    return $html;
  }

}
