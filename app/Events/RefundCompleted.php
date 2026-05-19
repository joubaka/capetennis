<?php

namespace App\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RefundCompleted
{
    use Dispatchable, SerializesModels;

    public Model $refund;
    public array $context;

    public function __construct(Model $refund, array $context = [])
    {
        $this->refund = $refund;
        $this->context = $context;
    }
}
