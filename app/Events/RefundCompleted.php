<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RefundCompleted
{
    use Dispatchable, SerializesModels;

    public $refund;
    public $context;

    public function __construct($refund, array $context = [])
    {
        $this->refund = $refund;
        $this->context = $context;
    }
}

