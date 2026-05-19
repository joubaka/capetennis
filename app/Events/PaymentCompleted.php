<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCompleted
{
    use Dispatchable, SerializesModels;

    public $payment;
    public $context;

    public function __construct($payment, array $context = [])
    {
        $this->payment = $payment;
        $this->context = $context;
    }
}

