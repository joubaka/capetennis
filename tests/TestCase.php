<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed required Spatie roles so hasRole()/hasAnyRole() never throws
        // "There is no role named X for guard web" during feature tests.
        if (class_exists(Role::class) && \Schema::hasTable('roles')) {
            foreach (['super-user', 'admin', 'convenor', 'player'] as $roleName) {
                Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            }
        }
    }
}
