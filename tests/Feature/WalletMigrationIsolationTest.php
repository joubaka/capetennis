<?php

namespace Tests\Feature;

use Bavix\Wallet\WalletConfigure;
use Illuminate\Database\Migrations\Migrator;
use Tests\TestCase;

class WalletMigrationIsolationTest extends TestCase
{
    public function test_application_uses_only_its_own_wallet_migrations(): void
    {
        $this->assertFalse(WalletConfigure::isRunsMigrations());

        $walletMigrationPath = realpath(base_path('vendor/bavix/laravel-wallet/database'));
        $registeredPaths = array_map(
            static fn (string $path): string|false => realpath($path),
            app(Migrator::class)->paths(),
        );

        $this->assertNotFalse($walletMigrationPath);
        $this->assertNotContains($walletMigrationPath, $registeredPaths);
    }
}
