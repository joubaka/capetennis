<?php

namespace App\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed
{
    use Dispatchable, SerializesModels;

    public Model $payment;
    public array $context;
    public ?string $error;

    public function __construct(Model $payment, array $context = [], ?string $error = null)
    {
        $this->payment = $payment;
        $this->context = $context;
        $this->error = $error;
    }
}
