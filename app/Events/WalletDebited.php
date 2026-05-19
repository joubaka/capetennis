<?php

namespace App\Events;

use App\Models\WalletTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletDebited
{
    use Dispatchable, SerializesModels;

    public WalletTransaction $transaction;
    public array $context;

    public function __construct(WalletTransaction $transaction, array $context = [])
    {
        $this->transaction = $transaction;
        $this->context = $context;
    }
}
