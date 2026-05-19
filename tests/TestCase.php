<?php

namespace Tests;

use Bavix\Wallet\WalletConfigure;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        // Prevent bavix/laravel-wallet from publishing its own `wallets` table
        // migration. The application uses a custom App\Models\Wallet instead.
        WalletConfigure::ignoreMigrations();

        parent::setUp();
    }
}
