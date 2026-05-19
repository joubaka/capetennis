<?php

namespace App\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCompleted
{
    use Dispatchable, SerializesModels;

    public Model $payment;
    public array $context;

    public function __construct(Model $payment, array $context = [])
    {
        $this->payment = $payment;
        $this->context = $context;
    }
}
