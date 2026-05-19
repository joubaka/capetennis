<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed
{
    use Dispatchable, SerializesModels;

    public $payment;
    public $context;
    public $error;

    public function __construct($payment, array $context = [], ?string $error = null)
    {
        $this->payment = $payment;
        $this->context = $context;
        $this->error = $error;
    }
}

