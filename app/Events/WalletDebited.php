<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletDebited
{
    use Dispatchable, SerializesModels;

    public $transaction;
    public $context;

    public function __construct($transaction, array $context = [])
    {
        $this->transaction = $transaction;
        $this->context = $context;
    }
}

